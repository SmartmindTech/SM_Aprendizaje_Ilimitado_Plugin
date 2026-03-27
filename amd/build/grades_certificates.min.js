/**
 * Grades & Certificates page — language selector.
 *
 * @module     local_sm_graphics_plugin/grades_certificates
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Update all certificate download links with the chosen language.
     * @param {string} lang
     */
    function updateLinks(lang) {
        document.querySelectorAll('[data-download-cert], [data-download-all]').forEach(function(link) {
            var href = link.getAttribute('href');
            if (!href) {
                return;
            }
            // Replace the certlang parameter.
            href = href.replace(/certlang=[a-z_]+/, 'certlang=' + lang);
            link.setAttribute('href', href);
        });
    }

    return {
        init: function() {
            var selector = document.getElementById('smgp-certlang');
            if (!selector) {
                return;
            }
            selector.addEventListener('change', function() {
                updateLinks(selector.value);
            });
        }
    };
});
