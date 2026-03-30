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
 * Client-side search, type and category filtering for the frontpage course catalogue.
 *
 * @module     theme_smartmind/catalogue_search
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    CATALOGUE: '[data-region="smartmind-catalogue"]',
    CARD: '[data-region="catalogue-card"]',
    CATEGORY_BADGES: '[data-region="smartmind-category-badges"]',
    CAT_BADGE: '.smartmind-cat-badge',
    TYPE_BADGES: '[data-region="smartmind-type-badges"]',
    TYPE_BADGE: '.smartmind-type-badge',
    SEARCH_INPUT: '[data-action="catalogue-search"]',
    COUNT: '[data-region="catalogue-count"]',
};

/**
 * Initialise the catalogue search, type and category filtering.
 */
export const init = () => {
    const catalogue = document.querySelector(SELECTORS.CATALOGUE);
    if (!catalogue) {
        return;
    }

    const cards = catalogue.querySelectorAll(SELECTORS.CARD);
    const catContainer = document.querySelector(SELECTORS.CATEGORY_BADGES);
    const typeContainer = document.querySelector(SELECTORS.TYPE_BADGES);
    const input = document.querySelector(SELECTORS.SEARCH_INPUT);
    const countEl = document.querySelector(SELECTORS.COUNT);

    let activeCategoryId = 'all';
    let activeTypeId = 'all';

    /** Apply search, type and category filters and update the visible count. */
    const applyFilters = () => {
        const query = input ? input.value.toLowerCase().trim() : '';
        let visible = 0;
        cards.forEach(card => {
            const name = (card.dataset.fullname || '').toLowerCase();
            const catId = card.dataset.categoryid;
            const typeId = (card.dataset.typeid || '').toLowerCase();
            const matchesSearch = !query || name.includes(query);
            const matchesCategory = activeCategoryId === 'all' || catId === activeCategoryId;
            const matchesType = activeTypeId === 'all' || typeId === activeTypeId;
            const show = matchesSearch && matchesCategory && matchesType;
            card.style.display = show ? '' : 'none';
            if (show) {
                visible++;
            }
        });
        if (countEl) {
            countEl.textContent = visible + ' resultados';
        }
    };

    // Search filtering.
    if (input) {
        input.addEventListener('input', applyFilters);
    }

    // Category badge filtering.
    if (catContainer) {
        catContainer.addEventListener('click', (e) => {
            const badge = e.target.closest(SELECTORS.CAT_BADGE);
            if (!badge) {
                return;
            }
            catContainer.querySelectorAll(SELECTORS.CAT_BADGE).forEach(b => {
                b.classList.remove('smartmind-cat-badge--active');
            });
            badge.classList.add('smartmind-cat-badge--active');
            activeCategoryId = badge.dataset.categoryid;
            applyFilters();
        });
    }

    // Type badge filtering.
    if (typeContainer) {
        typeContainer.addEventListener('click', (e) => {
            const badge = e.target.closest(SELECTORS.TYPE_BADGE);
            if (!badge) {
                return;
            }
            typeContainer.querySelectorAll(SELECTORS.TYPE_BADGE).forEach(b => {
                b.classList.remove('smartmind-type-badge--active');
            });
            badge.classList.add('smartmind-type-badge--active');
            activeTypeId = badge.dataset.typeid;
            applyFilters();
        });
    }
};
