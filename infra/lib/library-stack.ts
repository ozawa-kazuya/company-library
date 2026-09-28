import * as path from 'path';
import * as cdk from 'aws-cdk-lib';
import { Duration, RemovalPolicy } from 'aws-cdk-lib';
import * as acm from 'aws-cdk-lib/aws-certificatemanager';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as ecr_assets from 'aws-cdk-lib/aws-ecr-assets';
import * as ecs from 'aws-cdk-lib/aws-ecs';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as route53 from 'aws-cdk-lib/aws-route53';
import * as route53targets from 'aws-cdk-lib/aws-route53-targets';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';
import { Construct } from 'constructs';

export class LibraryStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    const domainName = String(
      this.node.tryGetContext('domainName') ?? process.env.LIBRARY_DOMAIN ?? '',
    ).trim();
    if (domainName === '') {
      throw new Error(
        'HTTPS のため infra/cdk.json の context.domainName（または LIBRARY_DOMAIN）に公開ドメインを入れてください。例: library.example.co.jp',
      );
    }

    const hostedZoneName = String(this.node.tryGetContext('hostedZoneName') ?? '').trim();
    const certificateArn = String(this.node.tryGetContext('certificateArn') ?? '').trim();

    const vpc = new ec2.Vpc(this, 'Vpc', {
      maxAzs: 2,
      natGateways: 0,
      subnetConfiguration: [
        {
          name: 'public',
          subnetType: ec2.SubnetType.PUBLIC,
          cidrMask: 24,
        },
        {
          name: 'isolated',
          subnetType: ec2.SubnetType.PRIVATE_ISOLATED,
          cidrMask: 24,
        },
      ],
    });

    const albSg = new ec2.SecurityGroup(this, 'AlbSg', {
      vpc,
      description: 'ALB inbound HTTP and HTTPS',
      allowAllOutbound: true,
    });
    albSg.addIngressRule(ec2.Peer.anyIpv4(), ec2.Port.tcp(80), 'HTTP');
    albSg.addIngressRule(ec2.Peer.anyIpv4(), ec2.Port.tcp(443), 'HTTPS');

    const appSg = new ec2.SecurityGroup(this, 'AppSg', {
      vpc,
      description: 'Fargate from ALB',
      allowAllOutbound: true,
    });
    appSg.addIngressRule(albSg, ec2.Port.tcp(80), 'ALB to app');

    const dbSg = new ec2.SecurityGroup(this, 'DbSg', {
      vpc,
      description: 'RDS from Fargate',
      allowAllOutbound: false,
    });
    dbSg.addIngressRule(appSg, ec2.Port.tcp(3306), 'app to RDS');

    const appKeySecret = new secretsmanager.Secret(this, 'AppKey', {
      description: 'Laravel APP_KEY（base64: 無し。コンテナ起動時に付与）',
      generateSecretString: {
        passwordLength: 32,
        excludePunctuation: true,
        excludeCharacters: '"\'\\',
      },
      removalPolicy: RemovalPolicy.DESTROY,
    });

    const database = new rds.DatabaseInstance(this, 'Database', {
      engine: rds.DatabaseInstanceEngine.mysql({
        version: rds.MysqlEngineVersion.VER_8_0,
      }),
      instanceType: ec2.InstanceType.of(ec2.InstanceClass.T4G, ec2.InstanceSize.MICRO),
      credentials: rds.Credentials.fromGeneratedSecret('library_admin'),
      databaseName: 'company_library',
      vpc,
      vpcSubnets: { subnetType: ec2.SubnetType.PRIVATE_ISOLATED },
      securityGroups: [dbSg],
      publiclyAccessible: false,
      multiAz: false,
      allocatedStorage: 20,
      storageType: rds.StorageType.GP3,
      storageEncrypted: true,
      backupRetention: Duration.days(1),
      deletionProtection: false,
      removalPolicy: RemovalPolicy.DESTROY,
    });

    const dbSecret = database.secret;
    if (dbSecret === undefined) {
      throw new Error('RDS の接続シークレットがありません。');
    }

    const alb = new elbv2.ApplicationLoadBalancer(this, 'Alb', {
      vpc,
      internetFacing: true,
      securityGroup: albSg,
      vpcSubnets: { subnetType: ec2.SubnetType.PUBLIC },
    });

    let certificate: acm.ICertificate;
    if (certificateArn !== '') {
      certificate = acm.Certificate.fromCertificateArn(this, 'Cert', certificateArn);
    } else if (hostedZoneName !== '') {
      const zone = route53.HostedZone.fromLookup(this, 'Zone', {
        domainName: hostedZoneName,
      });
      certificate = new acm.Certificate(this, 'Cert', {
        domainName,
        validation: acm.CertificateValidation.fromDns(zone),
      });
      new route53.ARecord(this, 'Alias', {
        zone,
        recordName:
          domainName === hostedZoneName
            ? undefined
            : domainName.endsWith(`.${hostedZoneName}`)
              ? domainName.slice(0, -(hostedZoneName.length + 1))
              : domainName,
        target: route53.RecordTarget.fromAlias(new route53targets.LoadBalancerTarget(alb)),
      });
    } else {
      certificate = new acm.Certificate(this, 'Cert', {
        domainName,
        validation: acm.CertificateValidation.fromDns(),
      });
    }

    alb.addListener('Http', {
      port: 80,
      protocol: elbv2.ApplicationProtocol.HTTP,
      open: false,
      defaultAction: elbv2.ListenerAction.redirect({
        protocol: 'HTTPS',
        port: '443',
        permanent: true,
      }),
    });

    const listener = alb.addListener('Https', {
      port: 443,
      protocol: elbv2.ApplicationProtocol.HTTPS,
      certificates: [certificate],
      open: false,
      sslPolicy: elbv2.SslPolicy.RECOMMENDED_TLS,
    });

    const cluster = new ecs.Cluster(this, 'Cluster', {
      vpc,
      containerInsightsV2: ecs.ContainerInsights.DISABLED,
    });

    const image = new ecr_assets.DockerImageAsset(this, 'AppImage', {
      directory: path.join(__dirname, '../..'),
      file: 'Dockerfile',
      platform: ecr_assets.Platform.LINUX_ARM64,
      exclude: ['infra/cdk.out', 'infra/node_modules', 'infra/dist'],
    });

    const taskDefinition = new ecs.FargateTaskDefinition(this, 'Task', {
      cpu: 256,
      memoryLimitMiB: 512,
      runtimePlatform: {
        cpuArchitecture: ecs.CpuArchitecture.ARM64,
        operatingSystemFamily: ecs.OperatingSystemFamily.LINUX,
      },
    });

    const logGroup = new logs.LogGroup(this, 'AppLogs', {
      retention: logs.RetentionDays.THREE_DAYS,
      removalPolicy: RemovalPolicy.DESTROY,
    });

    const container = taskDefinition.addContainer('web', {
      image: ecs.ContainerImage.fromDockerImageAsset(image),
      logging: ecs.LogDrivers.awsLogs({
        streamPrefix: 'library',
        logGroup,
      }),
      environment: {
        APP_NAME: '図書貸出管理システム',
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        APP_URL: `https://${domainName}`,
        SESSION_SECURE_COOKIE: 'true',
        APP_LOCALE: 'ja',
        APP_FALLBACK_LOCALE: 'ja',
        LOG_CHANNEL: 'stderr',
        LOG_LEVEL: 'info',
        DB_CONNECTION: 'mysql',
        SESSION_DRIVER: 'database',
        SESSION_LIFETIME: '120',
        CACHE_STORE: 'database',
        QUEUE_CONNECTION: 'database',
        TRUSTED_PROXIES: '*',
        MAIL_MAILER: 'smtp',
        RETURN_LOCATION_RESTRICTED: 'true',
        RETURN_ALLOW_LOCALHOST: 'false',
      },
      secrets: {
        APP_KEY: ecs.Secret.fromSecretsManager(appKeySecret),
        DB_HOST: ecs.Secret.fromSecretsManager(dbSecret, 'host'),
        DB_PORT: ecs.Secret.fromSecretsManager(dbSecret, 'port'),
        DB_DATABASE: ecs.Secret.fromSecretsManager(dbSecret, 'dbname'),
        DB_USERNAME: ecs.Secret.fromSecretsManager(dbSecret, 'username'),
        DB_PASSWORD: ecs.Secret.fromSecretsManager(dbSecret, 'password'),
      },
      healthCheck: {
        command: ['CMD-SHELL', 'curl -fsS http://127.0.0.1/up || exit 1'],
        interval: Duration.seconds(30),
        timeout: Duration.seconds(5),
        retries: 3,
        startPeriod: Duration.seconds(90),
      },
    });
    container.addPortMappings({ containerPort: 80, protocol: ecs.Protocol.TCP });

    const service = new ecs.FargateService(this, 'Service', {
      cluster,
      taskDefinition,
      desiredCount: 1,
      assignPublicIp: true,
      vpcSubnets: { subnetType: ec2.SubnetType.PUBLIC },
      securityGroups: [appSg],
      circuitBreaker: { enable: true, rollback: true },
      minHealthyPercent: 0,
      maxHealthyPercent: 100,
      healthCheckGracePeriod: Duration.seconds(120),
    });

    listener.addTargets('App', {
      port: 80,
      protocol: elbv2.ApplicationProtocol.HTTP,
      targets: [service],
      healthCheck: {
        path: '/up',
        healthyHttpCodes: '200',
        interval: Duration.seconds(30),
        timeout: Duration.seconds(5),
        healthyThresholdCount: 2,
        unhealthyThresholdCount: 3,
      },
      deregistrationDelay: Duration.seconds(30),
    });

    new cdk.CfnOutput(this, 'AppUrl', {
      value: `https://${domainName}`,
      description: 'アプリ URL（HTTPS）',
    });
    new cdk.CfnOutput(this, 'LoadBalancerDns', {
      value: alb.loadBalancerDnsName,
      description: 'DNS をこの ALB に向ける（Route53 未使用時）',
    });
    new cdk.CfnOutput(this, 'ClusterName', { value: cluster.clusterName });
    new cdk.CfnOutput(this, 'ServiceName', { value: service.serviceName });
    new cdk.CfnOutput(this, 'AppKeySecretArn', { value: appKeySecret.secretArn });
    new cdk.CfnOutput(this, 'DatabaseSecretArn', { value: dbSecret.secretArn });
  }
}
