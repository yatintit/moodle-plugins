<?php
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
 * Question Finder block class.
 *
 * Provides a site-wide question search tool for administrators and managers.
 * Searches question name and question text across all courses, returning
 * clickable links directly into the Question Bank.
 *
 * @package    block_question_finder
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Question Finder block.
 *
 * @package    block_question_finder
 * @copyright  2024 Antigravity
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_question_finder extends block_base {

    /**
     * Initialise block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_question_finder');
    }

    /**
     * Block can appear in all page types.
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * Allow multiple instances.
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * No block-level configuration needed.
     */
    public function has_config() {
        return false;
    }

    /**
     * Build and return block content.
     *
     * @return stdClass|null
     */
    public function get_content() {
        global $CFG, $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->footer = '';

        // Only admins and managers with viewall can use this block.
        $syscontext = context_system::instance();
        if (!has_capability('moodle/question:viewall', $syscontext)) {
            $this->content->text = '';
            return $this->content;
        }

        $searchurl = new moodle_url('/blocks/question_finder/search.php');
        $sesskey   = sesskey();

        // ---------------------------------------------------------------
        // Render HTML: search form + results container.
        // ---------------------------------------------------------------
        $html = <<<HTML
<div class="qf-block-wrapper" id="qf_block_wrapper" style="font-family: inherit;">

    <!-- Search Form -->
    <div class="qf-search-form mb-3">
        <div class="input-group">
            <input
                type="text"
                id="qf_search_input"
                class="form-control"
                placeholder="Type question text..."
                autocomplete="off"
                style="border-radius: 6px 0 0 6px; border: 1.5px solid #ced4da; padding: 8px 12px; font-size: 0.9rem;"
                aria-label="Search questions"
            >
            <div class="input-group-append">
                <button
                    id="qf_search_btn"
                    class="btn btn-primary"
                    type="button"
                    style="border-radius: 0 6px 6px 0; padding: 8px 16px; font-weight: 600;"
                >
                    <i class="fa fa-search mr-1"></i> Find
                </button>
            </div>
        </div>
        <p class="text-muted small mt-1 mb-0" style="font-size: 0.78rem;">
            <i class="fa fa-info-circle"></i> Searches question name &amp; text across all courses.
        </p>
    </div>

    <!-- Results Area -->
    <div id="qf_results_area" style="display:none;">
        <div id="qf_results_count" class="text-muted small mb-2" style="font-size:0.8rem;"></div>
        <div id="qf_results_list"
             style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; background:#fff;">
        </div>
    </div>

    <!-- Loading spinner -->
    <div id="qf_loading" style="display:none; text-align:center; padding: 15px 0; color: #6c757d;">
        <i class="fa fa-spinner fa-spin mr-1"></i> Searching...
    </div>

    <!-- Error area -->
    <div id="qf_error" class="alert alert-warning small py-2 px-3 mb-0" style="display:none; border-radius:6px; font-size:0.85rem;"></div>

</div>
HTML;

        // ---------------------------------------------------------------
        // Inline JavaScript — plain fetch() compatible with Moodle 4.0+
        // ---------------------------------------------------------------
        $js = <<<JS
(function() {
    var searchUrl = '{$searchurl}';
    var sesskey   = '{$sesskey}';

    function doSearch() {
        var query = document.getElementById('qf_search_input').value.trim();
        var resultsArea  = document.getElementById('qf_results_area');
        var resultsList  = document.getElementById('qf_results_list');
        var resultsCount = document.getElementById('qf_results_count');
        var loading      = document.getElementById('qf_loading');
        var errorBox     = document.getElementById('qf_error');

        // Reset state.
        resultsArea.style.display = 'none';
        errorBox.style.display    = 'none';
        errorBox.textContent      = '';

        if (!query) {
            errorBox.textContent = 'Please enter a search term.';
            errorBox.style.display = 'block';
            return;
        }

        loading.style.display = 'block';

        var formData = new FormData();
        formData.append('q', query);
        formData.append('sesskey', sesskey);

        fetch(searchUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function(resp) { return resp.json(); })
        .then(function(data) {
            loading.style.display = 'none';

            if (data.error) {
                errorBox.textContent = data.error;
                errorBox.style.display = 'block';
                return;
            }

            if (!data.results || data.results.length === 0) {
                errorBox.textContent = 'No questions found matching your search.';
                errorBox.style.display = 'block';
                return;
            }

            var count = data.results.length;
            resultsCount.innerHTML = '<strong>' + count + '</strong> question(s) found';

            window.qfCurrentResults = data.results;
            window.qfRenderPage(1);
            resultsArea.style.display = 'block';
        })
        .catch(function(err) {
            loading.style.display = 'none';
            errorBox.textContent = 'Search failed. Please try again.';
            errorBox.style.display = 'block';
        });
    }

    window.qfRenderPage = function(pageNumber) {
        var recordsPerPage = 4;
        var currentPage = pageNumber;
        var resultsList = document.getElementById('qf_results_list');
        var data = window.qfCurrentResults;
        
        var start = (currentPage - 1) * recordsPerPage;
        var end = start + recordsPerPage;
        var pageData = data.slice(start, end);

        var html = '<table class="table table-hover table-sm mb-0" style="font-size:0.82rem;">';
        html += '<thead class="thead-light">';
        html += '<tr>';
        html += '<th style="min-width:180px;">Question</th>';
        html += '<th>Type</th>';
        html += '<th>Category</th>';
        html += '<th>Course</th>';
        html += '<th style="min-width:60px;text-align:center;">Preview</th>';
        html += '<th style="min-width:60px;text-align:center;">Bank</th>';
        html += '<th style="min-width:60px;text-align:center;">Edit</th>';
        html += '</tr>';
        html += '</thead><tbody>';

        pageData.forEach(function(q) {
            var typeIcon = getTypeIcon(q.qtype);
            var rowTitle = escapeHtml(q.name);
            var shortName = rowTitle.length > 60 ? rowTitle.substring(0, 58) + '…' : rowTitle;

            html += '<tr>';
            // Question name — clicking it goes to the question bank.
            html += '<td class="align-middle">';
            html += '<a href="' + escapeHtml(q.bankurl) + '" title="' + rowTitle + '" style="font-weight:600;color:#0f6cbf;text-decoration:none;">';
            html += shortName;
            html += '</a>';
            html += '</td>';
            // Type badge.
            html += '<td class="align-middle">';
            html += '<span class="badge badge-secondary" style="font-size:0.72rem;">' + typeIcon + ' ' + escapeHtml(q.qtype) + '</span>';
            html += '</td>';
            // Category.
            html += '<td class="align-middle text-muted" style="word-break: break-word;" title="' + escapeHtml(q.category) + '">';
            html += escapeHtml(q.category);
            html += '</td>';
            // Course.
            html += '<td class="align-middle" style="word-break: break-word;" title="' + escapeHtml(q.course) + '">';
            html += '<a href="' + escapeHtml(q.courseurl) + '" style="color:#555;text-decoration:none;" title="Go to course">';
            html += escapeHtml(q.course);
            html += '</a>';
            html += '</td>';
            // Preview button (popup).
            html += '<td class="align-middle text-center">';
            if (q.previewurl) {
                html += '<a href="javascript:void(0);" onclick="window.open(\'' + escapeHtml(q.previewurl) + '\', \'questionpreview\', \'width=800,height=600,scrollbars=yes\'); return false;" class="btn btn-sm btn-outline-info" style="border-radius:5px;padding:2px 8px;font-size:0.75rem;" title="Preview Question">';
                html += '<i class="fa fa-eye"></i>';
                html += '</a>';
            }
            html += '</td>';
            // Bank button.
            html += '<td class="align-middle text-center">';
            if (q.bankurl) {
                html += '<a href="' + escapeHtml(q.bankurl) + '" class="btn btn-sm btn-outline-secondary" style="border-radius:5px;padding:2px 8px;font-size:0.75rem;" title="Open Category in Bank" target="_blank">';
                html += '<i class="fa fa-list"></i>';
                html += '</a>';
            }
            html += '</td>';
            // Edit button.
            html += '<td class="align-middle text-center">';
            html += '<a href="' + escapeHtml(q.editurl) + '" class="btn btn-sm btn-outline-primary" style="border-radius:5px;padding:2px 8px;font-size:0.75rem;" title="Edit Question" target="_blank">';
            html += '<i class="fa fa-pencil"></i>';
            html += '</a>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        
        // Add Pagination Controls
        var totalPages = Math.ceil(data.length / recordsPerPage);
        if (totalPages > 1) {
            html += '<div class="mt-3 d-flex justify-content-between align-items-center" style="font-size:0.85rem; background: #f8f9fa; padding: 10px; border-radius: 5px;">';
            html += '<div><strong>Page ' + currentPage + ' of ' + totalPages + '</strong></div>';
            html += '<div>';
            
            if (currentPage > 1) {
                html += '<button type="button" class="btn btn-sm btn-secondary mr-2" style="margin-right:5px;" onclick="window.qfRenderPage(' + (currentPage - 1) + ')">&laquo; Prev</button>';
            }
            if (currentPage < totalPages) {
                html += '<button type="button" class="btn btn-sm btn-secondary" onclick="window.qfRenderPage(' + (currentPage + 1) + ')">Next &raquo;</button>';
            }
            
            html += '</div></div>';
        }

        resultsList.innerHTML = html;
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getTypeIcon(qtype) {
        var icons = {
            'multichoice': '🔘',
            'truefalse':   '✅',
            'shortanswer': '✏️',
            'numerical':   '🔢',
            'essay':       '📝',
            'match':       '🔗',
            'ddwtos':      '🖱️',
            'calculated':  '🧮',
            'gapselect':   '🕳️',
        };
        return icons[qtype] || '❓';
    }

    // Bind events once DOM is ready.
    function bindEvents() {
        var btn   = document.getElementById('qf_search_btn');
        var input = document.getElementById('qf_search_input');
        if (btn)   btn.addEventListener('click', doSearch);
        if (input) input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') doSearch();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEvents);
    } else {
        bindEvents();
    }
})();
JS;

        $PAGE->requires->js_amd_inline($js);

        $this->content->text = $html;
        return $this->content;
    }
}
