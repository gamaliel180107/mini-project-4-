/**
 * INVENTORY PRO - CLIENT JAVASCRIPT
 */

document.addEventListener('DOMContentLoaded', function () {
    // Demo account credential filler
    document.querySelectorAll('.demo-pill').forEach(function (pill) {
        pill.addEventListener('click', function () {
            const u = this.getAttribute('data-user');
            const p = this.getAttribute('data-pass');
            const userInput = document.getElementById('username');
            const passInput = document.getElementById('password');
            if (userInput && passInput) {
                userInput.value = u;
                passInput.value = p;
                userInput.focus();
            }
        });
    });

    // Auto dismiss alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 400);
        }, 6000);
    });

    // Mobile sidebar toggle
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.app-sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // Dynamic stock preview on stock out / in forms
    const productSelect = document.getElementById('product_id');
    const stockPreview = document.getElementById('currentStockPreview');
    const qtyInput = document.getElementById('quantity');
    const remainingPreview = document.getElementById('remainingStockPreview');

    if (productSelect && stockPreview) {
        function updateStockPreview() {
            const selectedOpt = productSelect.options[productSelect.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.stock !== undefined) {
                const stock = parseInt(selectedOpt.dataset.stock, 10);
                stockPreview.textContent = stock;

                if (qtyInput && remainingPreview) {
                    const qty = parseInt(qtyInput.value, 10) || 0;
                    const isOut = document.body.dataset.pageType === 'stock-out';
                    if (isOut) {
                        const remaining = stock - qty;
                        remainingPreview.textContent = remaining;
                        if (remaining < 0) {
                            remainingPreview.style.color = '#ef4444';
                            remainingPreview.textContent = remaining + ' (Melebihi Stok!)';
                        } else {
                            remainingPreview.style.color = '#10b981';
                        }
                    }
                }
            } else {
                stockPreview.textContent = '-';
            }
        }

        productSelect.addEventListener('change', updateStockPreview);
        if (qtyInput) {
            qtyInput.addEventListener('input', updateStockPreview);
        }
        updateStockPreview();
    }
});
