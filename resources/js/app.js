import './bootstrap';
import * as bootstrap from 'bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Initialize Bootstrap popovers
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggerList.forEach(function (el) {
        new bootstrap.Popover(el);
    });

    // Auto-dismiss alerts after 5 seconds
    const alertList = document.querySelectorAll('.alert-dismissible');
    alertList.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // =========================================================
    // VICOBA Form Validation System
    // =========================================================
    // All forms with [data-validate] attribute are auto-validated.
    // Required fields: any input/select/textarea inside the form
    // whose label contains <span class="text-danger">*</span>.
    // =========================================================

    initFormValidation();

    function initFormValidation() {
        document.querySelectorAll('form[data-validate]').forEach(function (form) {
            form.setAttribute('novalidate', 'novalidate');

            form.addEventListener('submit', function (e) {
                if (!validateForm(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });

            // Live validation on blur and input
            getRequiredFields(form).forEach(function (field) {
                field.addEventListener('blur', function () {
                    validateField(field);
                });
                field.addEventListener('input', function () {
                    if (field.classList.contains('is-invalid')) {
                        validateField(field);
                    }
                });
            });
        });
    }

    function getRequiredFields(form) {
        const fields = [];
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.type === 'hidden' || field.type === 'checkbox' || field.type === 'radio') return;
            if (field.disabled) return;
            if (!isFieldRequired(field)) return;
            fields.push(field);
        });
        return fields;
    }

    function isFieldRequired(field) {
        const container = field.closest('.mb-3, .mb-4, .row, .col-md-6, .col-sm-4, .col-6, .form-group');
        if (!container) return false;
        const label = container.querySelector('label');
        if (!label) return false;
        return label.querySelector('.text-danger') !== null;
    }

    function validateForm(form) {
        let isValid = true;
        const firstInvalidField = getRequiredFields(form).find(function (field) {
            return !validateField(field);
        });
        if (firstInvalidField) {
            firstInvalidField.focus();
        }
        return isValid;
    }

    function validateField(field) {
        const value = field.value.trim();
        const isValid = value.length > 0;
        const container = field.closest('.mb-3, .mb-4, .row, .col-md-6, .col-sm-4, .col-6, .form-group');

        if (!isValid) {
            field.classList.add('is-invalid');
            field.style.borderColor = '#dc3545';
            let feedback = container ? container.querySelector('.js-validation-message') : null;
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback js-validation-message d-block';
                feedback.style.fontSize = '0.8rem';
                feedback.style.color = '#dc3545';
                feedback.style.marginTop = '0.25rem';
                // Insert after the field or its parent input-group
                const parent = field.closest('.input-group') || field;
                parent.parentNode.insertBefore(feedback, parent.nextSibling);
            }
            const fieldName = getFieldName(field);
            feedback.textContent = fieldName + ' is required.';
        } else {
            field.classList.remove('is-invalid');
            field.style.borderColor = '';
            const feedback = container ? container.querySelector('.js-validation-message') : null;
            if (feedback) feedback.remove();
        }

        return isValid;
    }

    function getFieldName(field) {
        const id = field.id;
        if (id) {
            const label = document.querySelector('label[for="' + id + '"]');
            if (label) {
                return label.textContent.replace(/\s*\*\s*$/, '').trim();
            }
        }
        const name = field.name || 'This field';
        return name.replace(/_/g, ' ').replace(/\[.*\]/, '').replace(/\b\w/g, function (c) {
            return c.toUpperCase();
        });
    }
});
