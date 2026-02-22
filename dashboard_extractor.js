// =====================================================
// API-FOOTBALL DASHBOARD - PLAYER ID EXTRACTOR v2
// Con autoguardado cada 100 páginas
// Paste this in Chrome DevTools Console (F12 > Console)
// while on: dashboard.api-football.com/soccer/ids/players
// =====================================================

(async function () {
    const results = {};

    function waitForTable(ms = 500) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function isProcessing() {
        const proc = document.querySelector('#dataTablePlayers_processing');
        return proc && proc.style.display !== 'none';
    }

    async function waitUntilReady() {
        while (isProcessing()) await waitForTable(300);
        await waitForTable(400);
    }

    function downloadJSON(suffix = '') {
        const total = Object.keys(results).length;
        const blob = new Blob([JSON.stringify(results, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `api_dashboard_players${suffix}.json`;
        a.click();
        URL.revokeObjectURL(url);
        console.log(`%c[SAVED] ${total} jugadores → api_dashboard_players${suffix}.json`, 'color:green;font-weight:bold');
    }

    // Set 100 entries per page
    const lengthSelect = document.querySelector('select[name="dataTablePlayers_length"]');
    if (lengthSelect) {
        lengthSelect.value = '100';
        lengthSelect.dispatchEvent(new Event('change'));
        await waitUntilReady();
        console.log('[*] Set to 100 entries per page');
    }

    let pageNum = 1;

    while (true) {
        // Check if session expired (redirect to login or empty table)
        if (document.location.href.includes('login')) {
            console.error('[!] Session expired! Saving progress...');
            downloadJSON('_partial_page' + pageNum);
            break;
        }

        const rows = document.querySelectorAll('#dataTablePlayers tbody tr');
        if (rows.length === 0) {
            console.warn('[!] Empty table — possible session expiry or end of data. Saving...');
            downloadJSON('_partial_page' + pageNum);
            break;
        }

        let pageCount = 0;
        rows.forEach(row => {
            const tds = row.querySelectorAll('td');
            if (tds.length >= 3) {
                const country = tds[0].innerText.trim();
                const name = tds[1].innerText.trim();
                const id = tds[2].innerText.trim();
                if (id && /^\d+$/.test(id)) {
                    results[id] = { name, country };
                    pageCount++;
                }
            }
        });

        const total = Object.keys(results).length;
        console.log(`[+] Page ${pageNum}: ${pageCount} players | Total: ${total}`);

        // Autosave every 100 pages
        if (pageNum % 100 === 0) {
            console.log(`%c[AUTOSAVE] Page ${pageNum} checkpoint...`, 'color:orange');
            downloadJSON('_checkpoint_page' + pageNum);
        }

        const nextBtn = document.querySelector('#dataTablePlayers_next:not(.disabled)');
        if (nextBtn) {
            nextBtn.click();
            pageNum++;
            await waitUntilReady();
        } else {
            console.log('%c[*] ALL DONE! Last page reached. Downloading final file...', 'color:lime;font-size:14px;font-weight:bold');
            downloadJSON('');
            break;
        }
    }
})();
