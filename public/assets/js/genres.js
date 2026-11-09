(() => {
    const form = document.querySelector('#genre-form');
    if (!form) return;

    const search = form.querySelector('#genre-search');
    const genreOptions = [...form.querySelectorAll('.genre-option')];
    const moodOptions = [...form.querySelectorAll('.mood-choice')];
    const count = form.querySelector('[data-selected-count]');
    const summary = form.querySelector('[data-selected-summary]');

    const updateMoodOptions = () => {
        if (moodOptions.length === 0) return;

        const selectedGenres = new Set(
            genreOptions
                .filter((label) => label.querySelector('input[type="checkbox"]')?.checked)
                .map((label) => Number(label.querySelector('input').value))
        );
        const matching = moodOptions.filter((option) =>
            option.dataset.moodGenres.split(',').some((id) => selectedGenres.has(Number(id)))
        );
        const visibleOptions = selectedGenres.size > 0 && matching.length > 0 ? matching : moodOptions;
        let hasVisibleSelection = false;

        moodOptions.forEach((option) => {
            option.hidden = !visibleOptions.includes(option);
            const radio = option.querySelector('input[type="radio"]');
            if (option.hidden && radio.checked) radio.checked = false;
            if (!option.hidden && radio.checked) hasVisibleSelection = true;
        });

        if (!hasVisibleSelection) {
            visibleOptions[0].querySelector('input[type="radio"]').checked = true;
        }
    };

    const updateSelected = () => {
        const checkedGenres = genreOptions.filter((label) => label.querySelector('input[type="checkbox"]')?.checked);
        if (count) count.textContent = String(checkedGenres.length);

        if (summary) {
            summary.replaceChildren(...checkedGenres.map((label) => {
                const chip = document.createElement('span');
                chip.textContent = label.querySelector('span')?.textContent.trim() ?? '';
                return chip;
            }));
        }

        updateMoodOptions();
    };

    form.addEventListener('change', (event) => {
        if (event.target.matches('input[name="genres[]"]')) updateSelected();
    });

    search?.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase();
        genreOptions.forEach((label) => {
            label.hidden = term !== '' && !label.dataset.genre.includes(term);
        });
    });

    updateSelected();
})();
