#!/usr/bin/env node
import * as cdk from 'aws-cdk-lib';
import { LibraryStack } from '../lib/library-stack';

const app = new cdk.App();

new LibraryStack(app, 'CompanyLibrary', {
  env: {
    account: process.env.CDK_DEFAULT_ACCOUNT,
    region: process.env.CDK_DEFAULT_REGION ?? 'ap-northeast-1',
  },
  description: '社内図書貸出（Fargate + ALB HTTPS + RDS MySQL）。未デプロイの定義。',
});
