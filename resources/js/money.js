const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/** Formats integer cents as BRL currency. */
export function formatMoney(cents) {
    return brl.format((Number(cents) || 0) / 100);
}

/**
 * Parses a user typed amount ("12,50", "1.250,00", "12.5") into integer cents.
 * Returns 0 when the text is not a valid positive amount.
 */
export function parseCents(text) {
    let value = String(text ?? '').trim().replace(/[^\d.,]/g, '');

    if (value.includes(',')) {
        value = value.replace(/\./g, '').replace(',', '.');
    }

    const number = Number.parseFloat(value);

    return Number.isFinite(number) && number > 0 ? Math.round(number * 100) : 0;
}

/** Integer cents to an editable "12,50" string. */
export function centsToInput(cents) {
    return ((Number(cents) || 0) / 100).toFixed(2).replace('.', ',');
}
