import { formatOverdueDuration } from '@/utils/overdueDisplay';

export function getCurrentLoan(book) {
    if (!book?.loans?.length) {
        return null;
    }

    return book.loans[0];
}

export function getLoanDisplayInfo(loan) {
    if (!loan?.borrowed_at) {
        return null;
    }

    const today = new Date();
    const borrowDate = new Date(loan.borrowed_at);

    const finalDueDate = loan.due_date
        ? new Date(loan.due_date)
        : new Date(borrowDate.getTime() + 14 * 24 * 60 * 60 * 1000);

    const diffMaxLoanMs = finalDueDate.getTime() - borrowDate.getTime();
    const isOverdue = finalDueDate.getTime() < today.getTime();

    let badgeText = '0日目';
    let badgeColorClass = 'bg-emerald-50 text-emerald-600 border-emerald-200';

    if (isOverdue) {
        badgeColorClass =
            'bg-red-100 text-red-600 border-red-300 font-black animate-pulse shadow-sm';
        badgeText =
            formatOverdueDuration(today.getTime() - finalDueDate.getTime()) || '期限切れ';
    } else {
        const elapsedDays = Math.floor(
            (today.getTime() - borrowDate.getTime()) / (1000 * 60 * 60 * 24),
        );
        badgeText = `${elapsedDays}日目`;
    }

    const durationDays = Math.floor(diffMaxLoanMs / (1000 * 60 * 60 * 24));

    let durationLabel = '（2週間貸出）';

    if (loan.duration_days === 7) {
        durationLabel = '（1週間貸出）';
    } else if (loan.duration_days === 14) {
        durationLabel = '（2週間貸出）';
    } else if (loan.duration_days === 30 || durationDays >= 28) {
        durationLabel = '（1ヶ月貸出）';
    } else if (durationDays <= 7) {
        durationLabel = '（1週間貸出）';
    }

    const formatDateText = (date) => date.toLocaleDateString();

    return {
        badgeText,
        badgeColorClass,
        borrowedAtText: formatDateText(borrowDate),
        finalDueDateText: formatDateText(finalDueDate),
        durationLabel,
        isOverdue,
    };
}

export function getUserEmpId(user) {
    if (user?.user_code) {
        return user.user_code;
    }

    if (user?.email) {
        return user.email.split('@')[0].toUpperCase();
    }

    return `ID-${user?.id ?? '?'}`;
}

export function matchesQuery(text, query) {
    if (!query) {
        return true;
    }

    return (text || '').toLowerCase().includes(query.toLowerCase());
}
