(function () {
    const tabs = document.querySelectorAll('[data-hall-tab]');
    if (tabs.length === 0) {
        return;
    }

    function activate(targetId) {
        tabs.forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.hallTab === targetId);
        });

        document.querySelectorAll('.hall-tab-panel').forEach((panel) => {
            const isActive = panel.id === targetId;
            panel.hidden = !isActive;
            panel.classList.toggle('active', isActive);
        });
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => activate(tab.dataset.hallTab));
    });
})();
