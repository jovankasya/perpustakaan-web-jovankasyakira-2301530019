// Utility functions for the application
class PerpustakaanApp {
    static showLoading(element) {
        element.innerHTML = '<p>Memuat data...</p>';
    }

    static showError(element, message) {
        element.innerHTML = `<p class="error">Error: ${message}</p>`;
    }

    static formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID');
    }

    static formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(amount);
    }

    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Auto-save functionality for forms
class AutoSave {
    constructor(formId, saveCallback) {
        this.form = document.getElementById(formId);
        this.saveCallback = saveCallback;
        this.init();
    }

    init() {
        const inputs = this.form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', PerpustakaanApp.debounce(() => {
                this.save();
            }, 1000));
        });
    }

    save() {
        const formData = new FormData(this.form);
        const data = Object.fromEntries(formData);
        this.saveCallback(data);
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PerpustakaanApp, AutoSave };
}