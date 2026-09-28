import { BOOK_CATEGORIES } from '@/constants/bookCategories';

export default function BookCategoryPicker({
    id = 'book-category',
    value,
    onChange,
    disabled = false,
}) {
    return (
        <div
            id={id}
            role="radiogroup"
            aria-label="カテゴリ"
            className="flex flex-wrap gap-2"
        >
            {BOOK_CATEGORIES.map((category) => {
                const selected = value === category;

                return (
                    <button
                        key={category}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        disabled={disabled}
                        onClick={() => onChange(category)}
                        className={`px-3 py-1.5 rounded-full text-xs font-black border transition-colors disabled:opacity-50 ${
                            selected
                                ? 'bg-[#00897b] border-[#00695c] text-white'
                                : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                        }`}
                    >
                        {category}
                    </button>
                );
            })}
        </div>
    );
}
