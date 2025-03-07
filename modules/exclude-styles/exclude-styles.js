(function() {
    'use strict';
    
    class VoxelExcludeStyles {
        constructor() {
            this.initialized = false;
            this.checkTinyMCEInterval = null;
            this.debug = false;
            this.elementMap = {
                'exclude-h1': {text: 'Heading 1', button: 'formatselect', formatName: 'h1'},
                'exclude-h2': {text: 'Heading 2', button: 'formatselect', formatName: 'h2'},
                'exclude-h3': {text: 'Heading 3', button: 'formatselect', formatName: 'h3'},
                'exclude-h4': {text: 'Heading 4', button: 'formatselect', formatName: 'h4'},
                'exclude-h5': {text: 'Heading 5', button: 'formatselect', formatName: 'h5'},
                'exclude-h6': {text: 'Heading 6', button: 'formatselect', formatName: 'h6'},
                'exclude-p': {text: 'Paragraph', button: 'formatselect', formatName: 'p'},
                'exclude-bold': {text: 'Bold', button: 'bold', icon: 'bold'},
                'exclude-italic': {text: 'Italic', button: 'italic', icon: 'italic'},
                'exclude-bullist': {text: 'Bulleted list', button: 'bullist', icon: 'bullist'},
                'exclude-numlist': {text: 'Numbered list', button: 'numlist', icon: 'numlist'},
                'exclude-link': {text: 'Insert/edit link', button: 'link', icon: 'link'},
                'exclude-unlink': {text: 'Remove link', button: 'unlink', icon: 'unlink'},
                'exclude-strikethrough': {text: 'Strikethrough', button: 'strikethrough', icon: 'strikethrough'},
                'exclude-hr': {text: 'Horizontal line', button: 'hr', icon: 'hr'},
                'exclude-blockquote': {text: 'Blockquote', button: 'blockquote', formatName: 'blockquote'},
                'exclude-pre': {text: 'Preformatted', button: 'formatselect', formatName: 'pre'},
                'exclude-formatselect': {text: 'Format', button: 'formatselect'},
                'exclude-styleselect': {text: 'Styles', button: 'styleselect'},
                'exclude-fontselect': {text: 'Font Family', button: 'fontselect'},
                'exclude-fontsizeselect': {text: 'Font Sizes', button: 'fontsizeselect'},
                'exclude-alignleft': {text: 'Align left', button: 'alignleft', icon: 'alignleft'},
                'exclude-aligncenter': {text: 'Align center', button: 'aligncenter', icon: 'aligncenter'},
                'exclude-alignright': {text: 'Align right', button: 'alignright', icon: 'alignright'},
                'exclude-forecolor': {text: 'Text color', button: 'forecolor'},
                'exclude-backcolor': {text: 'Background color', button: 'backcolor'}
            };
            
            this.ariaLabelMap = {
                'bold': 'Bold',
                'italic': 'Italic',
                'bullist': 'Bulleted list',
                'numlist': 'Numbered list',
                'link': 'Insert/edit link',
                'unlink': 'Remove link',
                'strikethrough': 'Strikethrough',
                'hr': 'Horizontal line',
                'alignleft': 'Align left',
                'aligncenter': 'Align center', 
                'alignright': 'Align right',
                'forecolor': 'Text color',
                'backcolor': 'Background color'
            };
            
            this.formatTextPatterns = {
                'h1': ['Heading 1'],
                'h2': ['Heading 2'],
                'h3': ['Heading 3'],
                'h4': ['Heading 4'],
                'h5': ['Heading 5'],
                'h6': ['Heading 6'],
                'pre': ['Preformatted'],
                'blockquote': ['Blockquote'],
                'p': ['Paragraph']
            };
            
            this.excludedFormats = [];
            this.methodsWorking = {
                buttonRemoval: false,
                menuObserver: false
            };
        }

        log(...args) {
            if (this.debug) console.log('[Voxel Exclude Styles]', ...args);
        }

        logMethodWorking(method, details = '') {
            if (this.methodsWorking[method] !== true) {
                this.methodsWorking[method] = true;
                this.log(`✅ METHOD WORKING: ${method}`, details);
            }
        }

        init() {
            this.setupFormatMenuObserver();
            
            if (typeof window.tinymce !== 'undefined') {
                if (window.tinymce.editors.length > 0) {
                    this.processTinyMCE();
                }
                
                window.tinymce.on('AddEditor', e => {
                    e.editor.on('init', () => this.processTinyMCE());
                });
            }
            
            const observer = new MutationObserver(() => {
                if (this.isTinyMCEAvailable()) {
                    this.processTinyMCE();
                }
            });
            
            observer.observe(document.body, { 
                childList: true, 
                subtree: true,
                attributes: true,
                attributeFilter: ['class']
            });
        }
        
        isTinyMCEAvailable() {
            return typeof window.tinymce !== 'undefined' && window.tinymce.editors.length > 0;
        }

        findExcludeClasses() {
            const excludeClasses = new Set();
            try {
                const selectors = [
                    '[class*="exclude-"]', 
                    '.ts-form-group[class*="exclude-"]',
                    '.elementor-element[class*="exclude-"]',
                    '.vx-group[class*="exclude-"]',
                    'div[class*="field-key-"][class*="exclude-"]'
                ].join(', ');
                
                const elements = document.querySelectorAll(selectors);
                for (let i = 0; i < elements.length; i++) {
                    const element = elements[i];
                    const classList = element.classList;
                    for (let j = 0; j < classList.length; j++) {
                        const className = classList[j];
                        if (className.startsWith('exclude-')) {
                            excludeClasses.add(className);
                        }
                    }
                }
            } catch (e) {
                this.log('Error finding exclude classes:', e);
            }
            
            return Array.from(excludeClasses);
        }
        
        processTinyMCE() {
            if (!this.isTinyMCEAvailable()) {
                return false;
            }
            
            const excludeClasses = this.findExcludeClasses();
            if (!excludeClasses.length) {
                return false;
            }
            
            const buttonExclusions = [];
            const formatExclusions = [];
            
            excludeClasses.forEach(className => {
                if (this.elementMap[className]) {
                    const item = this.elementMap[className];
                    
                    if (item.button && !buttonExclusions.includes(item.button)) {
                        buttonExclusions.push(item.button);
                    }
                    
                    if (item.formatName && !formatExclusions.includes(item.formatName)) {
                        formatExclusions.push(item.formatName);
                    }
                }
            });
            
            if (formatExclusions.length > 0) {
                this.excludedFormats = [
                    ...(this.excludedFormats || []),
                    ...formatExclusions
                ];
            }
            
            if (buttonExclusions.length > 0) {
                this.log(`Excluding buttons:`, buttonExclusions);
                
                tinymce.editors.forEach(editor => {
                    this.removeButtons(editor, buttonExclusions);
                });
                
                tinymce.on('AddEditor', e => {
                    e.editor.on('init', () => {
                        this.removeButtons(e.editor, buttonExclusions);
                    });
                });
            }
            
            this.initialized = true;
            return true;
        }
        
        removeButtons(editor, buttonsToRemove) {
            try {
                const editorContainer = editor.getContainer();
                if (!editorContainer) return;
                    
                let buttonsFound = 0;
                
                buttonsToRemove.forEach(button => {
                    const ariaLabelText = this.ariaLabelMap[button];
                    if (ariaLabelText) {
                        const buttonElements = editorContainer.querySelectorAll(`[aria-label*="${ariaLabelText}"]`);
                        buttonElements.forEach(btnElement => {
                            if (btnElement.closest('.mce-btn')) {
                                btnElement.closest('.mce-btn').style.display = 'none';
                                buttonsFound++;
                                this.log(`Removed button by aria-label: ${ariaLabelText}`);
                            }
                        });
                        
                        if (this.elementMap['exclude-' + button] && this.elementMap['exclude-' + button].icon) {
                            const iconClass = 'mce-i-' + this.elementMap['exclude-' + button].icon;
                            const iconElements = editorContainer.querySelectorAll(`.${iconClass}`);
                            iconElements.forEach(iconEl => {
                                if (iconEl.closest('.mce-btn')) {
                                    iconEl.closest('.mce-btn').style.display = 'none';
                                    buttonsFound++;
                                    this.log(`Removed button by icon class: ${iconClass}`);
                                }
                            });
                        }
                    }
                });
                
                if (buttonsFound > 0) {
                    this.logMethodWorking('buttonRemoval', `Removed ${buttonsFound} buttons via DOM`);
                }
            } catch (e) {
                this.log('Error removing buttons:', e);
            }
        }

        setupFormatMenuObserver() {
            this.excludedFormats = this.excludedFormats || [];
            
            const menuObserver = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    if (mutation.addedNodes && mutation.addedNodes.length) {
                        Array.from(mutation.addedNodes).forEach(node => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                const menu = node.classList && node.classList.contains('mce-menu') ? 
                                    node : node.querySelector && node.querySelector('.mce-menu');
                                
                                if (menu) {
                                    this.processFormatMenu(menu);
                                }
                            }
                        });
                    }
                });
            });
            
            menuObserver.observe(document.body, { 
                childList: true,
                subtree: true
            });
            
            document.addEventListener('click', () => {
                setTimeout(() => {
                    document.querySelectorAll('.mce-menu').forEach(menu => 
                        this.processFormatMenu(menu)
                    );
                }, 10);
            }, true);
            
            this.log('Format menu observer set up successfully');
        }
        
        processFormatMenu(menuElement) {
            if (!menuElement || !this.excludedFormats || !this.excludedFormats.length) {
                return;
            }
            
            const menuItems = menuElement.querySelectorAll('.mce-menu-item');
            let itemsHidden = 0;
            
            menuItems.forEach(item => {
                if (item.hasAttribute('data-voxel-processed')) return;
                
                item.setAttribute('data-voxel-processed', 'true');
                const text = item.textContent || '';
                
                this.excludedFormats.forEach(format => {
                    const patterns = this.formatTextPatterns[format] || [];
                    
                    if (patterns.some(pattern => text.includes(pattern))) {
                        item.style.display = 'none';
                        item.style.visibility = 'hidden';
                        item.style.height = '0';
                        item.style.overflow = 'hidden';
                        item.setAttribute('aria-hidden', 'true');
                        item.classList.add('voxel-format-excluded');
                        itemsHidden++;
                    }
                });
            });
            
            if (itemsHidden > 0) {
                this.log(`Menu observer hidden items: ${itemsHidden}`);
                this.logMethodWorking('menuObserver', `Observer hidden ${itemsHidden} menu items`);
            }
        }
    }

    const voxelExcludeStyles = new VoxelExcludeStyles();
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => voxelExcludeStyles.init());
    } else {
        voxelExcludeStyles.init();
    }
})();