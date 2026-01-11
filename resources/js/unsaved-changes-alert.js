/**
 * Unsaved Changes Alert
 *
 * Prevents users from accidentally leaving forms with unsaved data
 * Shows custom Filament-styled confirmation dialog
 */

(function() {
    let formChanged = false;
    let formSubmitted = false;
    let pendingNavigation = null;
    let isShowingModal = false;

    // Track form changes
    function initUnsavedChangesDetection() {
        // Find all Filament form elements
        const forms = document.querySelectorAll('form[wire\\:submit], form.fi-form');

        forms.forEach(form => {
            // Listen for input changes on all form fields
            form.addEventListener('input', function(e) {
                // Ignore disabled fields and hidden fields
                if (!e.target.disabled && e.target.type !== 'hidden') {
                    formChanged = true;
                }
            }, true);

            // Listen for change events (for selects, checkboxes, radios, file uploads)
            form.addEventListener('change', function(e) {
                // Ignore disabled fields and hidden fields
                if (!e.target.disabled && e.target.type !== 'hidden') {
                    formChanged = true;
                }
            }, true);

            // Reset flag when form is submitted
            form.addEventListener('submit', function() {
                formSubmitted = true;
                formChanged = false;
            });
        });
    }

    // Create and show custom confirmation modal
    function showUnsavedChangesModal(callback) {
        if (isShowingModal) return;
        isShowingModal = true;

        // Create modal backdrop
        const backdrop = document.createElement('div');
        backdrop.style.cssText = `
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        `;

        // Create modal container
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: relative;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 28rem;
            width: 100%;
            margin: 1rem;
            padding: 1.5rem;
            animation: fadeIn 0.2s ease-out;
        `;

        // Check for dark mode
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) {
            modal.style.backgroundColor = 'rgb(31, 41, 55)';
        }

        // Modal content
        modal.innerHTML = `
            <style>
                @keyframes fadeIn {
                    from { opacity: 0; transform: scale(0.95); }
                    to { opacity: 1; transform: scale(1); }
                }
            </style>
            <div style="display: flex; align-items: flex-start; gap: 1rem;">
                <div style="flex-shrink: 0;">
                    <svg style="width: 1.5rem; height: 1.5rem; color: rgb(245, 158, 11);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div style="flex: 1;">
                    <h3 style="font-size: 1.125rem; font-weight: 600; color: ${isDark ? 'white' : 'rgb(17, 24, 39)'}; margin-bottom: 0.5rem;">
                        تغييرات غير محفوظة
                    </h3>
                    <p style="font-size: 0.875rem; color: ${isDark ? 'rgb(156, 163, 175)' : 'rgb(75, 85, 99)'};">
                        لديك تغييرات غير محفوظة. هل تريد المغادرة دون حفظ؟
                    </p>
                </div>
            </div>
            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" id="unsaved-cancel-btn" style="
                    padding: 0.5rem 1rem;
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: ${isDark ? 'rgb(209, 213, 219)' : 'rgb(55, 65, 81)'};
                    background-color: ${isDark ? 'rgb(55, 65, 81)' : 'white'};
                    border: 1px solid ${isDark ? 'rgb(75, 85, 99)' : 'rgb(209, 213, 219)'};
                    border-radius: 0.5rem;
                    cursor: pointer;
                    transition: all 0.15s;
                ">
                    إلغاء
                </button>
                <button type="button" id="unsaved-leave-btn" style="
                    padding: 0.5rem 1rem;
                    font-size: 0.875rem;
                    font-weight: 500;
                    color: white;
                    background-color: rgb(220, 38, 38);
                    border: none;
                    border-radius: 0.5rem;
                    cursor: pointer;
                    transition: all 0.15s;
                ">
                    المغادرة بدون حفظ
                </button>
            </div>
        `;

        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        // Add hover effects
        const cancelBtn = document.getElementById('unsaved-cancel-btn');
        const leaveBtn = document.getElementById('unsaved-leave-btn');

        cancelBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = isDark ? 'rgb(75, 85, 99)' : 'rgb(249, 250, 251)';
        });
        cancelBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = isDark ? 'rgb(55, 65, 81)' : 'white';
        });

        leaveBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgb(185, 28, 28)';
        });
        leaveBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'rgb(220, 38, 38)';
        });

        // Handle cancel button
        document.getElementById('unsaved-cancel-btn').addEventListener('click', function() {
            backdrop.remove();
            isShowingModal = false;
            if (callback) callback(false);
        });

        // Handle leave button
        document.getElementById('unsaved-leave-btn').addEventListener('click', function() {
            backdrop.remove();
            isShowingModal = false;
            formChanged = false;
            formSubmitted = true;
            if (callback) callback(true);
        });

        // Close on backdrop click
        backdrop.addEventListener('click', function(e) {
            if (e.target === backdrop) {
                backdrop.remove();
                isShowingModal = false;
                if (callback) callback(false);
            }
        });

        // Close on ESC key
        const escHandler = function(e) {
            if (e.key === 'Escape') {
                backdrop.remove();
                isShowingModal = false;
                document.removeEventListener('keydown', escHandler);
                if (callback) callback(false);
            }
        };
        document.addEventListener('keydown', escHandler);
    }

    // Intercept navigation attempts
    function interceptNavigation() {
        // Intercept all link clicks
        document.addEventListener('click', function(e) {
            if (formChanged && !formSubmitted && !isShowingModal) {
                const link = e.target.closest('a');
                if (link && link.href && !link.getAttribute('wire:click')) {
                    // Check if it's an external link or different page
                    const currentPath = window.location.pathname;
                    const targetPath = new URL(link.href, window.location.origin).pathname;

                    if (currentPath !== targetPath) {
                        e.preventDefault();
                        e.stopPropagation();

                        showUnsavedChangesModal(function(shouldLeave) {
                            if (shouldLeave) {
                                window.location.href = link.href;
                            }
                        });
                    }
                }
            }
        }, true);

        // Intercept browser back/forward buttons
        let isNavigatingAway = false;
        window.addEventListener('popstate', function(e) {
            if (formChanged && !formSubmitted && !isNavigatingAway) {
                history.pushState(null, '', window.location.href);

                showUnsavedChangesModal(function(shouldLeave) {
                    if (shouldLeave) {
                        isNavigatingAway = true;
                        history.back();
                    }
                });
            }
        });

        // Still use beforeunload for tab close/refresh
        window.addEventListener('beforeunload', function(e) {
            if (formChanged && !formSubmitted) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
    }

    // Listen to Livewire events
    document.addEventListener('livewire:init', () => {
        // Listen for successful form submissions
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            succeed(({ snapshot, effect }) => {
                // Check if this was a form submission action
                if (effect?.dispatches?.length > 0 || effect?.redirectTo) {
                    formSubmitted = true;
                    formChanged = false;
                }
            });
        });

        // Intercept Livewire navigation
        Livewire.hook('navigate', ({ url, history }) => {
            if (formChanged && !formSubmitted && !isShowingModal) {
                return new Promise((resolve, reject) => {
                    showUnsavedChangesModal(function(shouldLeave) {
                        if (shouldLeave) {
                            resolve();
                        } else {
                            reject();
                        }
                    });
                });
            }
        });
    });

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initUnsavedChangesDetection();
            interceptNavigation();
        });
    } else {
        initUnsavedChangesDetection();
        interceptNavigation();
    }

    // Re-initialize when Livewire updates the DOM (for SPAs)
    document.addEventListener('livewire:navigated', function() {
        formChanged = false;
        formSubmitted = false;
        isShowingModal = false;
        initUnsavedChangesDetection();
    });
})();
