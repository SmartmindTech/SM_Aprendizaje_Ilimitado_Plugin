// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Client-side search and category filtering for the frontpage course catalogue.
 *
 * @module     theme_smartmind/catalogue_search
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    CATALOGUE: '[data-region="smartmind-catalogue"]',
    CARD: '[data-region="catalogue-card"]',
    BADGES: '[data-region="smartmind-category-badges"]',
    BADGE: '.smartmind-badge',
};

/**
 * Initialise the catalogue search and category filtering.
 */
export const init = () => {
    const catalogue = document.querySelector(SELECTORS.CATALOGUE);
    if (!catalogue) {
        return;
    }

    const cards = catalogue.querySelectorAll(SELECTORS.CARD);
    const badgeContainer = document.querySelector(SELECTORS.BADGES);
    const input = document.querySelector('input[type="text"], input[type="search"]');

    let activeCategoryId = 'all';

    /** Apply both search and category filters. */
    const applyFilters = () => {
        const query = input ? input.value.toLowerCase().trim() : '';
        cards.forEach(card => {
            const name = (card.dataset.fullname || '').toLowerCase();
            const catId = card.dataset.categoryid;
            const matchesSearch = !query || name.includes(query);
            const matchesCategory = activeCategoryId === 'all' || catId === activeCategoryId;
            card.style.display = matchesSearch && matchesCategory ? '' : 'none';
        });
    };

    // Search filtering.
    if (input) {
        input.addEventListener('input', applyFilters);
    }

    // Category badge filtering.
    if (badgeContainer) {
        badgeContainer.addEventListener('click', (e) => {
            const badge = e.target.closest(SELECTORS.BADGE);
            if (!badge) {
                return;
            }

            // Update active state.
            badgeContainer.querySelectorAll(SELECTORS.BADGE).forEach(b => {
                b.classList.remove('smartmind-badge--active');
            });
            badge.classList.add('smartmind-badge--active');

            activeCategoryId = badge.dataset.categoryid;
            applyFilters();
        });
    }
};
