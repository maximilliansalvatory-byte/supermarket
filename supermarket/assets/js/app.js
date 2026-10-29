/**
 * FreshCart Supermarket - Customer Main Script
 * Handles AJAX shopping cart operations, quantity adjustments, and dynamic toast alerts.
 */

// Establish standard headers configuration including the CSRF token
function getAjaxHeaders() {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    const token = tokenMeta ? tokenMeta.getAttribute('content') : '';
    return {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': token
    };
}

/**
 * Global dynamic premium Toast notifier engine.
 * Spawns an animated glassmorphic banner that fades out automatically.
 */
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    // Select emoji and border class based on type
    let emoji = '✅';
    let typeClass = 'toast-custom-success';
    if (type === 'error') {
        emoji = '❌';
        typeClass = 'toast-custom-error';
    } else if (type === 'info') {
        emoji = 'ℹ️';
        typeClass = 'toast-custom-info';
    }
    
    // Create element
    const toast = document.createElement('div');
    toast.className = `toast-custom ${typeClass}`;
    toast.innerHTML = `
        <span class="fs-5">${emoji}</span>
        <div class="flex-grow-1 small text-dark-emphasis">${message}</div>
        <button type="button" class="btn-close ms-2 small" style="font-size:0.75rem;" onclick="this.parentElement.remove()"></button>
    `;
    
    container.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.add('show');
    }, 50);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 400);
    }, 4000);
}

/**
 * AJAX Add Product to Shopping Cart.
 */
function addToCart(productId, quantity = 1) {
    const headers = getAjaxHeaders();
    const body = `action=add&product_id=${productId}&quantity=${quantity}&csrf_token=${headers['X-CSRF-Token']}`;
    
    // Display quick loading state or trigger fetch
    fetch('/supermarket/customer/cart.php', {
        method: 'POST',
        headers: headers,
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Update the navbar badge
            const badge = document.getElementById('cartCountBadge');
            if (badge) {
                badge.innerText = data.total_items;
                // Animate bounce the badge to capture attention
                badge.style.transform = 'scale(1.3) translate(30%, -30%)';
                setTimeout(() => {
                    badge.style.transform = 'translate(50%, -50%)';
                }, 200);
            }
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error adding item to cart:', error);
        showToast('Failed to add product. Please check your connection.', 'error');
    });
}

/**
 * AJAX Update Cart Item Quantity on the Shopping Cart view.
 */
function updateCartQuantity(productId, newQty, totalRowsCountSelector) {
    const headers = getAjaxHeaders();
    const body = `action=update&product_id=${productId}&quantity=${newQty}&csrf_token=${headers['X-CSRF-Token']}`;
    
    fetch('/supermarket/customer/cart.php', {
        method: 'POST',
        headers: headers,
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Update row item total in UI
            const itemTotalCell = document.getElementById(`item-total-${productId}`);
            if (itemTotalCell && data.item_total_formatted) {
                itemTotalCell.innerText = data.item_total_formatted;
            }
            
            // Update order summaries in UI
            updateCartSummaryLayout(data);
            
            // Update navbar badge
            const badge = document.getElementById('cartCountBadge');
            if (badge) {
                badge.innerText = data.total_items;
            }
            
            showToast(data.message, 'success');
        } else {
            // Revert value in input
            const qtyInput = document.getElementById(`qty-input-${productId}`);
            if (qtyInput && data.current_stock !== undefined) {
                qtyInput.value = data.current_stock;
            }
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error updating cart:', error);
        showToast('Failed to update cart quantity.', 'error');
    });
}

/**
 * AJAX Remove Item from Shopping Cart.
 */
function removeCartItem(productId) {
    const headers = getAjaxHeaders();
    const body = `action=remove&product_id=${productId}&csrf_token=${headers['X-CSRF-Token']}`;
    
    fetch('/supermarket/customer/cart.php', {
        method: 'POST',
        headers: headers,
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Remove the product row with fade out effect
            const row = document.getElementById(`cart-row-${productId}`);
            if (row) {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                row.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    row.remove();
                    
                    // Check if cart is now empty
                    const cartTableBody = document.querySelector('.cart-table-body');
                    if (cartTableBody && cartTableBody.children.length === 0) {
                        location.reload(); // Reload to show empty cart banner
                    }
                }, 300);
            }
            
            // Update summaries
            updateCartSummaryLayout(data);
            
            // Update badge
            const badge = document.getElementById('cartCountBadge');
            if (badge) {
                badge.innerText = data.total_items;
            }
            
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error removing cart item:', error);
        showToast('Failed to remove item from cart.', 'error');
    });
}

/**
 * Helper to update pricing summaries elements.
 */
function updateCartSummaryLayout(data) {
    const subtotalEl = document.getElementById('cartSubtotal');
    const taxEl = document.getElementById('cartTax');
    const deliveryEl = document.getElementById('cartDelivery');
    const totalEl = document.getElementById('cartTotal');
    
    if (subtotalEl) subtotalEl.innerText = data.subtotal_formatted;
    if (taxEl) taxEl.innerText = data.tax_formatted;
    if (deliveryEl) deliveryEl.innerText = data.delivery_formatted;
    if (totalEl) totalEl.innerText = data.total_formatted;
}
