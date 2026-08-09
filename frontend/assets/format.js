/** Small shared formatting helpers used by every demo page. */
function fmtSom(minorUnits) {
    const major = Math.round(minorUnits / 100);
    return major.toLocaleString('uz-UZ').replace(/,/g, ' ') + " so'm";
}

function statusLabel(status) {
    const map = {
        due_today: 'Bugun',
        overdue: "Muddati o'tgan",
        reserve_soon: 'Tez orada',
        reserved: 'Zaxirada',
    };
    return map[status] || status;
}
