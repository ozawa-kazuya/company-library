export const USER_CODE_MAX = 3;
export const USER_CODE_HALF_WIDTH_ERROR = '社員番号は半角数字で入力してください。';
export const USER_CODE_LENGTH_ERROR = '社員番号は3桁以内で入力してください。';
export const PASSWORD_HALF_WIDTH_ERROR = 'パスワードは半角英数字で入力してください。';
export const PASSWORD_LENGTH_ERROR = 'パスワードは10文字以内で入力してください。';

export function sanitizeUserCode(value) {
    return value.replace(/\D/g, '').slice(0, USER_CODE_MAX);
}

export function userCodeInputError(value) {
    if (value === '') {
        return '';
    }

    if (value.length > USER_CODE_MAX) {
        return USER_CODE_LENGTH_ERROR;
    }

    if (!/^[0-9]+$/.test(value)) {
        return USER_CODE_HALF_WIDTH_ERROR;
    }

    return '';
}

export function passwordInputError(value) {
    if (value === '') {
        return '';
    }

    if (value.length > 10) {
        return PASSWORD_LENGTH_ERROR;
    }

    if (!/^[a-zA-Z0-9]+$/.test(value)) {
        return PASSWORD_HALF_WIDTH_ERROR;
    }

    return '';
}

export function loginInputClassName(hasError, extra = '') {
    const base =
        'w-full bg-white border rounded-md px-4 py-2.5 text-base text-gray-800 focus:ring-4 focus:outline-none shadow-sm transition-all';

    if (hasError) {
        return `${base} border-red-500 focus:border-red-500 focus:ring-red-100 ${extra}`.trim();
    }

    return `${base} border-gray-300 focus:border-blue-400 focus:ring-blue-100 ${extra}`.trim();
}
