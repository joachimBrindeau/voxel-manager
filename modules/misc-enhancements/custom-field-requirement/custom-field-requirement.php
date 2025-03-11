<?php
if (!defined('ABSPATH')) exit;

class VBM_CustomFieldRequirement_Enhancement {
    public function __construct() {
        add_action('wp_head', [$this, 'custom_field_requirement_styles']);
        add_action('wp_footer', [$this, 'custom_field_requirement_script']);
    }

    public function custom_field_requirement_styles() {
        ?>
        <style>
        .ts-form-group .is-required:not(.ts-char-counter),
        .ts-file-upload .is-required:not(.ts-char-counter),
        span.is-required:not(.ts-char-counter) {
            background-color: #8B0F0F1A !important;
            color: #8B0F0F !important;
            padding: 2px 10px !important;
            display: inline-block !important;
            border-radius: 4px !important;
        }
        .ts-char-counter {
            background-color: #0666351A !important;
            color: #066635 !important;
            padding: 2px 10px !important;
            display: inline-block !important;
            border-radius: 4px !important;
        }
        .is-required[data-optional="true"]:not(.ts-char-counter) {
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        </style>
        <?php
    }

    public function custom_field_requirement_script() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function handleRequirementLabels() {
                const requirementSpans = document.querySelectorAll('.is-required:not(.ts-char-counter)');
                requirementSpans.forEach(span => {
                    if (span.textContent.includes('Optional')) {
                        span.setAttribute('data-optional', 'true');
                    } else {
                        span.removeAttribute('data-optional');
                    }
                });
            }
            handleRequirementLabels();
            const observer = new MutationObserver(() => handleRequirementLabels());
            observer.observe(document.body, { childList: true, subtree: true });
        });
        </script>
        <?php
    }
}