/**
 * Nutronyx Admin Core — Shared Client-Side UI Controller
 * Handles responsive mobile navigation drawer, modals, toast alerts, confirmation dialogs, and interactions.
 */
(function() {
    'use strict';

    // --- 1. MOBILE SIDEBAR DRAWER CONTROLLER ---
    function initSidebar() {
        if (window.__sidebarDrawerInitialized || window.NutronyxSidebar) {
            return;
        }

        const toggleBtn = document.getElementById('mobileSidebarToggle');
        const sidebar = document.querySelector('.dash-sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const closeBtn = document.getElementById('sidebarCloseBtn');

        if (!sidebar) return;
        window.__sidebarDrawerInitialized = true;

        function openDrawer() {
            sidebar.classList.add('mobile-open');
            sidebar.classList.add('sidebar-drawer-open');
            if (backdrop) backdrop.classList.add('active');
            document.body.classList.add('sidebar-open-lock');
        }

        function closeDrawer() {
            sidebar.classList.remove('mobile-open');
            sidebar.classList.remove('sidebar-drawer-open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.classList.remove('sidebar-open-lock');
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (sidebar.classList.contains('mobile-open') || sidebar.classList.contains('sidebar-drawer-open')) {
                    closeDrawer();
                } else {
                    openDrawer();
                }
            });
        }

        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if (backdrop) backdrop.addEventListener('click', closeDrawer);

        // Close immediately on navigation link click
        const navLinks = sidebar.querySelectorAll('a:not(.no-close)');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeDrawer();
            });
        });

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && (sidebar.classList.contains('mobile-open') || sidebar.classList.contains('sidebar-drawer-open'))) {
                closeDrawer();
            }
        });
    }

    // --- 2. MODAL CONTROLLER ---
    window.NutronyxModal = {
        open: function(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.add('active');
            document.body.classList.add('modal-open-lock');
            
            // Focus first input if available
            const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 80);
            }
        },
        close: function(modalId) {
            const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
            if (!modal) return;
            modal.classList.remove('active');
            // If no other modals are active, unlock body
            if (!document.querySelector('.modal-overlay.active, .confirm-overlay.active')) {
                document.body.classList.remove('modal-open-lock');
            }
        }
    };

    // Global backdrop click and ESC key listeners for modals
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            window.NutronyxModal.close(e.target);
        }
        if (!e.target.closest('.action-dropdown-wrap')) {
            document.querySelectorAll('.action-dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
                m.style.top = '';
                m.style.bottom = '';
            });
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active, .confirm-overlay.active');
            if (activeModal) {
                activeModal.classList.remove('active');
                if (!document.querySelector('.modal-overlay.active, .confirm-overlay.active')) {
                    document.body.classList.remove('modal-open-lock');
                }
            }
        }
    });

    // --- 3. TOAST NOTIFICATION SERVICE ---
    window.NutronyxToast = {
        show: function(message, type = 'success', duration = 3500) {
            let container = document.getElementById('nutronyxToastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'nutronyxToastContainer';
                container.className = 'toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast-item toast-${type}`;
            
            const iconSvg = type === 'success' 
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>'
                : type === 'error'
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';

            toast.innerHTML = `
                <div class="toast-icon">${iconSvg}</div>
                <div class="toast-message">${message}</div>
                <button type="button" class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('toast-fade-out');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        },
        success: function(msg) { this.show(msg, 'success'); },
        error: function(msg) { this.show(msg, 'error', 4500); },
        info: function(msg) { this.show(msg, 'info'); }
    };

    // --- 4. CONFIRMATION DIALOG SERVICE ---
    window.NutronyxConfirm = function(options, legacyCallback, legacyIsDanger) {
        let title = 'Confirm Action';
        let message = 'Are you sure you want to proceed?';
        let confirmText = 'Confirm';
        let cancelText = 'Cancel';
        let isDanger = false;
        let onConfirm = () => {};

        if (typeof options === 'string') {
            message = options;
            title = legacyIsDanger ? 'Warning: Irreversible Action' : 'Confirm Action';
            isDanger = legacyIsDanger !== undefined ? legacyIsDanger : true;
            if (typeof legacyCallback === 'function') {
                onConfirm = legacyCallback;
            }
        } else if (typeof options === 'object' && options !== null) {
            title = options.title || title;
            message = options.message || message;
            confirmText = options.confirmText || confirmText;
            cancelText = options.cancelText || cancelText;
            isDanger = options.isDanger !== undefined ? options.isDanger : isDanger;
            onConfirm = typeof options.onConfirm === 'function' ? options.onConfirm : onConfirm;
        }

        let overlay = document.getElementById('nutronyxConfirmOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'nutronyxConfirmOverlay';
            overlay.className = 'confirm-overlay';
            document.body.appendChild(overlay);
        }

        overlay.innerHTML = `
            <div class="confirm-card">
                <div class="confirm-header">
                    <div class="confirm-icon ${isDanger ? 'confirm-danger' : 'confirm-info'}">
                        ${isDanger 
                            ? '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
                            : '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'}
                    </div>
                    <div style="flex: 1;">
                        <h3 class="confirm-title">${title}</h3>
                        <p class="confirm-message">${message}</p>
                    </div>
                </div>
                <div class="confirm-footer">
                    <button type="button" class="btn-secondary" id="confirmCancelBtn">${cancelText}</button>
                    <button type="button" class="${isDanger ? 'btn-danger' : 'btn-primary'}" id="confirmProceedBtn">${confirmText}</button>
                </div>
            </div>
        `;

        overlay.classList.add('active');
        document.body.classList.add('modal-open-lock');

        const proceedBtn = document.getElementById('confirmProceedBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');

        function cleanup() {
            overlay.classList.remove('active');
            if (!document.querySelector('.modal-overlay.active')) {
                document.body.classList.remove('modal-open-lock');
            }
        }

        proceedBtn.onclick = function() {
            cleanup();
            if (typeof onConfirm === 'function') onConfirm();
        };

        cancelBtn.onclick = cleanup;
        overlay.onclick = function(e) {
            if (e.target === overlay) cleanup();
        };
    };

    // --- SCROLL RESTORATION HELPER ---
    window.NutronyxScroll = {
        save: function(key = 'nutronyx_scroll') {
            try { sessionStorage.setItem(key, window.scrollY.toString()); } catch(e) {}
        },
        restore: function(key = 'nutronyx_scroll') {
            try {
                const pos = sessionStorage.getItem(key);
                if (pos !== null) {
                    window.scrollTo({ top: parseInt(pos, 10), behavior: 'instant' });
                    sessionStorage.removeItem(key);
                }
            } catch(e) {}
        }
    };

    // --- 5. CLIPBOARD COPY HELPER ---
    window.NutronyxCopy = function(text, btnElement) {
        if (!navigator.clipboard) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        } else {
            navigator.clipboard.writeText(text);
        }

        if (btnElement) {
            const originalHtml = btnElement.innerHTML;
            btnElement.innerHTML = '&check; Copied!';
            btnElement.classList.add('copied');
            setTimeout(() => {
                btnElement.innerHTML = originalHtml;
                btnElement.classList.remove('copied');
            }, 1800);
        }

        window.NutronyxToast.success(`Copied "${text}" to clipboard.`);
    };

    // --- 6. ADVANCED PRODUCT MODAL CONTROLLER ---
    window.NutronyxProduct = {
        openAdd: function() {
            const form = document.getElementById('productForm');
            if (form) form.reset();

            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = (val !== null && val !== undefined) ? val : '';
            };
            const setChecked = (id, val) => {
                const el = document.getElementById(id);
                if (el) {
                    if (el.type === 'checkbox') {
                        el.checked = Boolean(val);
                    } else {
                        el.value = val ? '1' : '0';
                    }
                }
            };

            const titleEl = document.getElementById('productModalTitle');
            if (titleEl) titleEl.textContent = 'Add product';
            setVal('productFormAction', 'create_product');
            setVal('productIdField', '');
            const submitBtn = document.getElementById('productSubmitBtn');
            if (submitBtn) submitBtn.textContent = 'Save Product';

            // Reset preview image
            const previewImg = document.getElementById('productPhotoPreview');
            if (previewImg) previewImg.src = '../assets/images/about-commitment-still-life.jpg';
            setVal('productImageUrl', 'assets/images/about-commitment-still-life.jpg');

            // Form Fields
            setVal('productTitle', '');
            setVal('productSlug', '');
            setVal('productShortDesc', '');
            setVal('productFullDesc', '');
            setVal('productRegularPrice', '');
            setVal('productSalePrice', '');
            setVal('productCostPrice', '');
            setVal('productSku', '');
            setVal('productStatus', 'ACTIVE');
            setVal('productBadge', '100% Natural Ingredients');
            setVal('productStockQty', '100');
            setChecked('productVisStore', true);
            setChecked('productVisCat', true);
            setChecked('productVisSearch', true);
            setChecked('productFeatured', false);

            // Optional extended fields
            setVal('productCurrency', 'INR');
            setChecked('productTrackInv', true);
            setChecked('productBackorders', false);
            setVal('productStockStatus', 'in_stock');
            setVal('productLowStock', '10');
            setVal('productLowStockThreshold', '10');
            setVal('productPosition', '0');
            setVal('productTaxRate', '0');

            const catEl = document.getElementById('productCategory');
            if (catEl && typeof this.updateSubcategories === 'function') {
                this.updateSubcategories(catEl);
            }

            const tbody = document.getElementById('variantsMatrixTbody');
            const matrixContainer = document.getElementById('generatedVariantsContainer');
            if (tbody) tbody.innerHTML = '';
            if (matrixContainer) matrixContainer.style.display = 'block';

            window.NutronyxModal.open('productModal');
        },

        openEdit: function(p) {
            if (!p) return;
            const form = document.getElementById('productForm');
            if (form) form.reset();

            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = (val !== null && val !== undefined) ? val : '';
            };
            const setChecked = (id, val) => {
                const el = document.getElementById(id);
                if (el) {
                    if (el.type === 'checkbox') {
                        el.checked = Boolean(val);
                    } else {
                        el.value = val ? '1' : '0';
                    }
                }
            };

            const titleEl = document.getElementById('productModalTitle');
            if (titleEl) titleEl.textContent = 'Edit product';
            setVal('productFormAction', 'update_product');
            setVal('productIdField', p.id || '');
            const submitBtn = document.getElementById('productSubmitBtn');
            if (submitBtn) submitBtn.textContent = 'Save changes';

            setVal('productTitle', p.title);
            setVal('productSlug', p.slug);
            setVal('productShortDesc', p.short_description);
            setVal('productFullDesc', p.description);
            setVal('productCategory', p.category_id);
            setVal('productStatus', (p.status || 'ACTIVE').toUpperCase());
            setVal('productBadge', p.badge || '100% Natural Ingredients');

            // Pricing
            const price = p.primary_price_amount ? (p.primary_price_amount / 100) : (p.min_price ? (p.min_price / 100) : '');
            setVal('productRegularPrice', price);

            const salePrice = p.sale_price_amount ? (p.sale_price_amount / 100) : (p.primary_compare_at_amount ? (p.primary_compare_at_amount / 100) : '');
            setVal('productSalePrice', salePrice);

            const costPrice = p.cost_price_amount ? (p.cost_price_amount / 100) : '';
            setVal('productCostPrice', costPrice);

            // SKU & Stock & Low Stock
            setVal('productSku', p.primary_sku || '');
            const totalStock = p.total_stock !== undefined ? p.total_stock : (p.primary_stock || 100);
            setVal('productStockQty', totalStock);
            const lowStock = p.low_stock_threshold !== undefined ? p.low_stock_threshold : (p.primary_low_stock_threshold || 10);
            setVal('productLowStockThreshold', lowStock);
            setVal('productPosition', p.position !== undefined ? p.position : 0);

            // Visibility & Channels
            setChecked('productVisStore', p.is_visible_storefront === 1 || p.is_visible_storefront === '1' || p.is_visible_storefront === undefined);
            setChecked('productVisCat', p.is_visible_catalog === 1 || p.is_visible_catalog === '1' || p.is_visible_catalog === undefined);
            setChecked('productVisSearch', p.is_visible_search === 1 || p.is_visible_search === '1' || p.is_visible_search === undefined);
            setChecked('productFeatured', p.is_featured === 1 || p.is_featured === '1');

            // Populate existing variants into matrix if present
            const tbody = document.getElementById('variantsMatrixTbody');
            const matrixContainer = document.getElementById('generatedVariantsContainer');
            if (tbody) {
                tbody.innerHTML = '';
                if (matrixContainer) matrixContainer.style.display = 'block';
                if (p.variants && p.variants.length > 0) {
                    p.variants.forEach(v => {
                        this.addNewVariantRow(v);
                    });
                }
            }

            // Image Preview
            const imgPath = p.image_url || 'assets/images/about-commitment-still-life.jpg';
            const fullImgPath = (imgPath.startsWith('http') || imgPath.startsWith('/')) ? imgPath : ('../' + imgPath);
            const previewImg = document.getElementById('productPhotoPreview');
            if (previewImg) previewImg.src = fullImgPath;
            setVal('productImageUrl', imgPath);

            window.NutronyxModal.open('productModal');
        },

        toggleAccordion: function(headerEl) {
            const item = headerEl.closest('.product-accordion-item');
            if (item) {
                item.classList.toggle('open');
            }
        },

        syncSlug: function() {
            const titleInput = document.getElementById('productTitle');
            const slugInput = document.getElementById('productSlug');
            if (!titleInput || !slugInput) return;
            const slug = titleInput.value.toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
            slugInput.value = slug;
        },

        handleImageSelect: function(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('productPhotoPreview');
                    const hidden = document.getElementById('productImageUrl');
                    if (preview) preview.src = e.target.result;
                    if (hidden) hidden.value = e.target.result;
                    window.NutronyxToast.success('Product image uploaded successfully.');
                };
                reader.readAsDataURL(input.files[0]);
            }
        },

        aiEnhance: function() {
            window.NutronyxToast.info('Enhancing photo with AI studio lighting & edge sharpening (-3 credits)...');
            setTimeout(() => {
                window.NutronyxToast.success('Photo enhanced! 4K resolution clarity applied.');
            }, 900);
        },

        aiMakePhotos: function() {
            window.NutronyxToast.info('Generating 4 photos (front, top, side, close-up) from main photo (-18 credits)...');
            setTimeout(() => {
                window.NutronyxToast.success('4 AI product photos generated and attached to gallery!');
            }, 1200);
        },

        aiMakeClips: function() {
            window.NutronyxToast.info('Rendering motion video clip of product (-90 credits)...');
            setTimeout(() => {
                window.NutronyxToast.success('360° motion clip generated and added to Media tab!');
            }, 1400);
        },

        aiWriteCopy: function() {
            const title = document.getElementById('productTitle').value.trim() || 'Botanical Formula';
            window.NutronyxToast.info(`Generating intelligent copywriting for "${title}" (-20 credits)...`);
            setTimeout(() => {
                const shortDesc = document.getElementById('productShortDesc');
                const fullDesc = document.getElementById('productFullDesc');
                if (shortDesc) shortDesc.value = `Artisanal single-origin ${title} crafted for peak wellness and everyday vitality with premium natural ingredients.`;
                if (fullDesc) fullDesc.value = `Experience the therapeutic potency of our signature ${title}. Carefully formulated from pure, sustainably sourced ingredients, each batch delivers exceptional balance, smooth flavor, and verifiable purity. Ideal for discerning wellness connoisseurs seeking clean nutrition.`;
                window.NutronyxToast.success('Short and Full descriptions drafted with AI!');
            }, 800);
        },

        aiAutofillListing: function() {
            this.aiWriteCopy();
            setTimeout(() => {
                const tags = document.getElementById('productTags');
                const collections = document.getElementById('productCollections');
                const title = document.getElementById('productTitle').value.trim() || 'wellness';
                if (tags) tags.value = `${title.toLowerCase()}, artisanal, premium, natural, bestsellers`;
                if (collections) collections.value = 'Signature Series 2026, Best Sellers, New Arrivals';
                window.NutronyxToast.success('Tags, collections, and SEO metadata auto-filled!');
            }, 900);
        },

        updateSubcategories: function(catSelect) {
            if (!catSelect) catSelect = document.getElementById('productCategory');
            if (!catSelect) return;
            const selectedOption = catSelect.options[catSelect.selectedIndex];
            const subcatInput = document.getElementById('productSubcategory');
            let datalist = document.getElementById('subcategorySuggestionsList');
            if (!datalist) {
                datalist = document.createElement('datalist');
                datalist.id = 'subcategorySuggestionsList';
                document.body.appendChild(datalist);
            }
            if (subcatInput) {
                subcatInput.setAttribute('list', 'subcategorySuggestionsList');
            }
            datalist.innerHTML = '';
            if (selectedOption && selectedOption.dataset.subcategories) {
                try {
                    const list = JSON.parse(selectedOption.dataset.subcategories);
                    if (Array.isArray(list)) {
                        list.forEach(sub => {
                            const opt = document.createElement('option');
                            opt.value = sub;
                            datalist.appendChild(opt);
                        });
                    }
                } catch(e) {}
            }
        },

        confirmDelete: function(productId, productTitle) {
            window.NutronyxConfirm({
                title: 'Delete Product',
                message: `Are you sure you want to delete "${productTitle}"? This will safely remove its variant SKUs and warehouse inventory records.`,
                confirmText: 'Yes, Delete',
                cancelText: 'Cancel',
                isDanger: true,
                onConfirm: function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'products.php';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="product_id" value="${productId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        },

        // --- NEW INTERACTIVE CATALOG METHODS (MATCHING SPECIFICATION) ---

        toggleVariantAccordion: function(productId, btn) {
            const subtable = document.getElementById('variant-subtable-' + productId);
            if (!subtable) return;
            const isOpen = subtable.classList.contains('open');
            if (isOpen) {
                subtable.classList.remove('open');
                if (btn) btn.classList.remove('open');
            } else {
                subtable.classList.add('open');
                if (btn) btn.classList.add('open');
            }
        },

        toggleActionMenu: function(menuId, event) {
            if (event) {
                event.stopPropagation();
            }
            const targetMenu = document.getElementById(menuId);
            const isShown = targetMenu && targetMenu.classList.contains('show');

            // Close all open action menus and reset inline positions
            document.querySelectorAll('.action-dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
                m.style.top = '';
                m.style.bottom = '';
            });

            if (!isShown && targetMenu) {
                targetMenu.classList.add('show');
                const rect = targetMenu.getBoundingClientRect();
                if (rect.bottom > window.innerHeight - 20) {
                    targetMenu.style.top = 'auto';
                    targetMenu.style.bottom = 'calc(100% + 4px)';
                } else {
                    targetMenu.style.top = 'calc(100% + 4px)';
                    targetMenu.style.bottom = 'auto';
                }
            }
        },

        toggleSelectAll: function(cb) {
            const rowCheckboxes = document.querySelectorAll('.product-select-checkbox');
            rowCheckboxes.forEach(box => {
                box.checked = cb.checked;
            });
            this.handleRowCheckbox();
        },

        handleRowCheckbox: function() {
            const selected = this.getSelectedIds();
            const badge = document.getElementById('selectedCountBadge');
            const countNum = document.getElementById('selectedCountNumber');
            const allCheckbox = document.getElementById('selectAllCheckbox');
            const totalBoxes = document.querySelectorAll('.product-select-checkbox').length;
            const bulkBtns = document.getElementById('bulkButtonsGroup');

            if (selected.length > 0) {
                if (badge) badge.style.display = 'inline-flex';
                if (countNum) countNum.textContent = selected.length;
                if (bulkBtns) bulkBtns.style.display = 'inline-flex';
            } else {
                if (badge) badge.style.display = 'none';
                if (bulkBtns) bulkBtns.style.display = 'none';
            }

            if (allCheckbox) {
                allCheckbox.checked = (selected.length > 0 && selected.length === totalBoxes);
                allCheckbox.indeterminate = (selected.length > 0 && selected.length < totalBoxes);
            }
        },

        getSelectedIds: function() {
            const checkedBoxes = document.querySelectorAll('.product-select-checkbox:checked');
            return Array.from(checkedBoxes).map(cb => cb.value);
        },

        executeBulk: function(subAction) {
            const selected = this.getSelectedIds();
            if (selected.length === 0) {
                window.NutronyxToast.warning('Please select at least one product using the checkboxes.');
                return;
            }

            if (subAction === 'delete') {
                window.NutronyxConfirm({
                    title: 'Delete Selected Products',
                    message: `Are you sure you want to permanently delete the ${selected.length} selected product(s)? This will clean associated variant records.`,
                    confirmText: 'Yes, Delete',
                    cancelText: 'Cancel',
                    isDanger: true,
                    onConfirm: () => {
                        this.submitBulkForm('delete', selected);
                    }
                });
                return;
            }

            this.submitBulkForm(subAction, selected);
        },

        submitBulkForm: function(subAction, ids) {
            document.getElementById('bulkSubactionField').value = subAction;
            document.getElementById('bulkSelectedIdsField').value = ids.join(',');
            document.getElementById('bulkForm').submit();
        },

        openBulkStockModal: function() {
            const selected = this.getSelectedIds();
            if (selected.length === 0) {
                window.NutronyxToast.warning('Please select products to adjust stock.');
                return;
            }
            window.NutronyxModal.open('bulkStockModal');
        },

        submitBulkStock: function() {
            const selected = this.getSelectedIds();
            const qty = document.getElementById('bulkStockQtyInput').value;
            const mode = document.getElementById('bulkStockMode').value;
            document.getElementById('bulkStockQtyField').value = qty;
            document.getElementById('bulkStockModeField').value = mode;
            this.submitBulkForm('update_stock', selected);
        },

        openBulkCategoryModal: function() {
            const selected = this.getSelectedIds();
            if (selected.length === 0) {
                window.NutronyxToast.warning('Please select products to reassign category.');
                return;
            }
            window.NutronyxModal.open('bulkCategoryModal');
        },

        submitBulkCategory: function() {
            const selected = this.getSelectedIds();
            const catId = document.getElementById('bulkCategorySelect').value;
            document.getElementById('bulkCategoryIdField').value = catId;
            this.submitBulkForm('change_category', selected);
        },

        toggleVisibility: function(productId, checkbox) {
            const originalState = checkbox.checked;
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'toggle_visibility');
            formData.append('product_id', productId);

            fetch('products.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const isVis = (data.is_visible !== undefined) ? (data.is_visible == 1) : originalState;
                    checkbox.checked = isVis;
                    // Synchronize all child variant toggles in nested subtable
                    const subtable = document.getElementById('variant-subtable-' + productId);
                    if (subtable) {
                        subtable.querySelectorAll('.toggle-switch input[type="checkbox"]').forEach(cb => {
                            cb.checked = isVis;
                        });
                    }
                    window.NutronyxToast.success(isVis ? 'Product published to storefront.' : 'Product hidden from storefront.');
                } else {
                    checkbox.checked = !originalState;
                    window.NutronyxToast.error(data.error || 'Failed to update visibility.');
                }
            })
            .catch(() => {
                checkbox.checked = !originalState;
                window.NutronyxToast.error('Network error updating visibility.');
            });
        },

        toggleVariantVisibility: function(variantId, checkbox) {
            const originalState = checkbox.checked;
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'toggle_variant_visibility');
            formData.append('variant_id', variantId);

            fetch('products.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.NutronyxToast.success(originalState ? 'Variant enabled.' : 'Variant disabled.');
                } else {
                    checkbox.checked = !originalState;
                    window.NutronyxToast.error(data.error || 'Failed to update variant visibility.');
                }
            })
            .catch(() => {
                checkbox.checked = !originalState;
                window.NutronyxToast.error('Network error updating variant visibility.');
            });
        },

        duplicate: function(productId) {
            window.NutronyxToast.info('Duplicating product and generating unique variant SKUs...');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'products.php';
            form.innerHTML = `
                <input type="hidden" name="action" value="duplicate_product">
                <input type="hidden" name="product_id" value="${productId}">
            `;
            document.body.appendChild(form);
            form.submit();
        },

        archiveProduct: function(productId) {
            window.location.href = 'products.php?toggle_status=ARCHIVED&id=' + encodeURIComponent(productId);
        },

        openRelations: function(productId, productTitle) {
            document.getElementById('relCurrentProductId').value = productId;
            document.getElementById('relationModalSubtitle').textContent = 'Relationships for: ' + productTitle;
            this.switchRelTab('related');
            window.NutronyxModal.open('relatedProductsModal');
        },

        switchRelTab: function(tabName) {
            window._currentRelTab = tabName;
            ['related', 'upsell', 'cross_sell'].forEach(t => {
                const btn = document.getElementById('tabRel' + (t === 'cross_sell' ? 'CrossSell' : (t === 'upsell' ? 'Upsell' : 'Related')));
                if (btn) {
                    if (t === tabName) {
                        btn.className = 'btn-emerald-brand';
                    } else {
                        btn.className = 'btn-emerald-outline';
                    }
                }
            });

            // Fetch current relations for product
            const pid = document.getElementById('relCurrentProductId').value;
            fetch('products.php?ajax=1&action=get_details&product_id=' + encodeURIComponent(pid))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.product && data.product.relations) {
                        const linked = data.product.relations[tabName] || [];
                        const linkedIds = linked.map(item => item.product_id);
                        document.querySelectorAll('.rel-candidate-item').forEach(item => {
                            const cid = item.dataset.id;
                            const chk = item.querySelector('.rel-checkbox');
                            if (cid === pid) {
                                item.style.display = 'none'; // prevent self reference
                            } else {
                                item.style.display = 'flex';
                                if (chk) chk.checked = linkedIds.includes(cid);
                            }
                        });
                    }
                });
        },

        filterRelCandidates: function(query) {
            const q = query.toLowerCase().trim();
            const pid = document.getElementById('relCurrentProductId').value;
            document.querySelectorAll('.rel-candidate-item').forEach(item => {
                const name = item.dataset.name || '';
                const cid = item.dataset.id;
                if (cid === pid) {
                    item.style.display = 'none';
                } else if (!q || name.includes(q)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        },

        saveCurrentRelations: function() {
            const pid = document.getElementById('relCurrentProductId').value;
            const tabName = window._currentRelTab || 'related';
            const selectedRelBoxes = document.querySelectorAll('.rel-checkbox:checked');
            const selectedIds = Array.from(selectedRelBoxes).map(cb => cb.value).filter(id => id !== pid);

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'save_relations');
            formData.append('product_id', pid);
            formData.append('relation_type', tabName);
            formData.append('related_ids', selectedIds.join(','));

            fetch('products.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.NutronyxToast.success(data.message || 'Relationships saved.');
                        window.NutronyxModal.close('relatedProductsModal');
                    } else {
                        window.NutronyxToast.error(data.error || 'Failed to save relationships.');
                    }
                });
        },

        openDetails: function(productId) {
            window.NutronyxModal.open('productDetailsDrawer');
            const content = document.getElementById('drawerContent');
            content.innerHTML = '<div style="text-align:center; padding: 40px; color: #9CA3AF;">Loading comprehensive product details...</div>';

            fetch('products.php?ajax=1&action=get_details&product_id=' + encodeURIComponent(productId))
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.product) {
                        content.innerHTML = '<div style="color: #DC2626; padding: 20px;">Product not found.</div>';
                        return;
                    }
                    const p = data.product;
                    document.getElementById('drawerProductTitle').textContent = p.title;
                    document.getElementById('drawerProductSku').textContent = 'SKU: ' + (p.primary_sku || 'N/A');
                    document.getElementById('drawerEditBtn').onclick = () => {
                        window.NutronyxModal.close('productDetailsDrawer');
                        window.NutronyxProduct.openEdit(p);
                    };

                    const minP = (p.min_price || p.primary_price_amount || 0) / 100;
                    const maxP = (p.max_price || p.min_price || p.primary_price_amount || 0) / 100;
                    const priceDisplay = (minP > 0 && maxP > minP)
                        ? `₹${minP.toLocaleString('en-IN')} – ₹${maxP.toLocaleString('en-IN')}`
                        : `₹${minP.toLocaleString('en-IN')}`;
                    const stock = p.total_stock || 0;

                    let html = `
                        <div style="display: flex; gap: 18px; margin-bottom: 24px; align-items: flex-start;">
                            <div style="width: 84px; height: 84px; border-radius: 10px; overflow: hidden; border: 1px solid #E5E7EB; flex-shrink: 0;">
                                <img src="${p.image_url ? (p.image_url.startsWith('http') || p.image_url.startsWith('/') ? p.image_url : '../' + p.image_url) : '../assets/images/about-commitment-still-life.jpg'}" style="width:100%; height:100%; object-fit:cover;">
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 4px;">
                                    <span class="category-badge-pill">${p.category_name || 'General'}</span>
                                    <span class="status-pill-dot ${stock <= 0 ? 'out-of-stock' : (p.status || 'ACTIVE').toLowerCase()}">
                                        <span class="dot"></span>
                                        <span>${stock <= 0 ? 'Out of Stock' : (p.status || 'ACTIVE')}</span>
                                    </span>
                                </div>
                                <div style="font-size: 20px; font-weight: 700; color: #111827; margin-top: 4px;">${priceDisplay}</div>
                                <div style="font-size: 13px; color: ${stock <= 0 ? '#EF4444' : '#10B981'}; font-weight: 500;">
                                    ${stock} units in inventory stock
                                </div>
                            </div>
                        </div>

                        <!-- Visibility Switches -->
                        <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 14px; margin-bottom: 20px;">
                            <div style="font-size: 12px; font-weight: 700; color: #4B5563; text-transform: uppercase; margin-bottom: 10px;">Storefront Visibility & Discovery</div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                                <div>Storefront: <strong>${p.is_visible_storefront == 1 ? 'Visible' : 'Hidden'}</strong></div>
                                <div>Catalog Listing: <strong>${p.is_visible_catalog == 1 ? 'Visible' : 'Hidden'}</strong></div>
                                <div>Search Indexed: <strong>${p.is_visible_search == 1 ? 'Yes' : 'No'}</strong></div>
                                <div>Featured Product: <strong>${p.is_featured == 1 ? 'Yes (Spotlight)' : 'No'}</strong></div>
                            </div>
                        </div>

                        <!-- Variants Subtable -->
                        <div style="margin-bottom: 24px;">
                            <div style="font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 10px;">Variants (${p.variants ? p.variants.length : 0})</div>
                            <table class="nested-variant-table">
                                <thead>
                                    <tr>
                                        <th>Variant</th>
                                        <th>SKU</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    if (p.variants && p.variants.length > 0) {
                        p.variants.forEach(v => {
                            html += `
                                <tr>
                                    <td><strong>${v.title}</strong></td>
                                    <td style="font-family: monospace;">${v.sku}</td>
                                    <td>₹${((v.price_amount || 0) / 100).toLocaleString('en-IN')}</td>
                                    <td style="color:${(v.quantity_on_hand || 0) <= 0 ? '#EF4444' : '#111827'}">${v.quantity_on_hand || 0}</td>
                                </tr>
                            `;
                        });
                    } else {
                        html += `<tr><td colspan="4" style="text-align:center; color:#9CA3AF;">No separate variants defined.</td></tr>`;
                    }

                    html += `
                                </tbody>
                            </table>
                        </div>

                        <!-- Description -->
                        <div style="margin-bottom: 24px;">
                            <div style="font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 6px;">Description</div>
                            <div style="font-size: 13.5px; color: #4B5563; line-height: 1.6;">${p.description || p.short_description || 'No description provided.'}</div>
                        </div>
                    `;

                    content.innerHTML = html;
                })
                .catch(() => {
                    content.innerHTML = '<div style="color: #DC2626; padding: 20px;">Failed to load product details.</div>';
                });
        },

        openExportModal: function() {
            const selected = this.getSelectedIds();
            const btnSelected = document.getElementById('btnExportSelected');
            const textSpan = document.getElementById('btnExportSelectedText');
            if (btnSelected) {
                btnSelected.style.display = (selected.length > 0) ? 'flex' : 'none';
                if (textSpan && selected.length > 0) {
                    textSpan.textContent = `Export Selected Checkboxes Only (${selected.length} items)`;
                }
            }
            window.NutronyxModal.open('exportModal');
        },

        exportSelected: function() {
            const selected = this.getSelectedIds();
            if (selected.length === 0) {
                window.NutronyxToast.warning('Please select at least one product checkbox to export.');
                return;
            }
            window.location.href = 'export.php?type=products&ids=' + encodeURIComponent(selected.join(','));
            window.NutronyxModal.close('exportModal');
        },

        updatePerPage: function(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', val);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        },

        updateSort: function(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', val);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        },

        toggleMoreFilters: function() {
            const panel = document.getElementById('moreFiltersPanel');
            if (panel) {
                panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
            }
        },

        addNewVariantRow: function(v = null) {
            const tbody = document.getElementById('variantsMatrixTbody');
            const container = document.getElementById('generatedVariantsContainer');
            if (!tbody) return;
            if (container) container.style.display = 'block';

            const idx = tbody.querySelectorAll('tr').length;
            const regPrice = document.getElementById('productRegularPrice')?.value || '499';
            const baseSku = (document.getElementById('productSku')?.value || 'NX-PROD').trim();
            const defStock = document.getElementById('productStockQty')?.value || '100';

            const id = v && v.id ? v.id : '';
            const title = v && v.title ? v.title : '';
            const sku = v && v.sku ? v.sku : (id ? '' : (baseSku ? baseSku + '-V' + (idx + 1) : ''));
            const price = v ? (v.price_rupees !== undefined ? v.price_rupees : ((v.price_amount || 0) / 100)) : regPrice;
            const stock = v ? (v.stock_quantity !== undefined ? v.stock_quantity : (v.quantity_on_hand !== undefined ? v.quantity_on_hand : defStock)) : defStock;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="hidden" name="variants[${idx}][id]" value="${id}">
                    <input type="text" name="variants[${idx}][title]" value="${title}" required placeholder="e.g. 200g or 400g" class="form-control" style="font-size:12.5px; padding:6px 10px;">
                </td>
                <td>
                    <input type="text" name="variants[${idx}][sku]" value="${sku}" placeholder="Auto SKU" class="form-control" style="font-size:12px; padding:6px 8px; font-family:monospace;">
                </td>
                <td>
                    <input type="number" step="0.01" name="variants[${idx}][price_rupees]" value="${price}" required class="form-control" style="font-size:12.5px; padding:6px 8px;">
                </td>
                <td>
                    <input type="number" name="variants[${idx}][stock_quantity]" value="${stock}" min="0" class="form-control" style="font-size:12.5px; padding:6px 8px;">
                </td>
                <td style="text-align: center;">
                    <button type="button" onclick="this.closest('tr').remove()" title="Remove variant" style="border:none; background:transparent; color:#EF4444; font-size:18px; font-weight:bold; cursor:pointer; padding:4px 8px; line-height:1;">&times;</button>
                </td>
            `;
            tbody.appendChild(tr);
        },

        generateVariantRowsFromInputs: function() {
            this.addNewVariantRow();
        },

        updatePosition: function(productId, position) {
            const formData = new FormData();
            formData.append('action', 'update_position');
            formData.append('product_id', productId);
            formData.append('position', position);

            fetch('products.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (window.NutronyxToast) {
                        window.NutronyxToast.success('Position sequence saved: #' + position);
                    }
                } else {
                    if (window.NutronyxToast) {
                        window.NutronyxToast.error(data.error || 'Failed to update position');
                    }
                }
            })
            .catch(err => {
                if (window.NutronyxToast) {
                    window.NutronyxToast.error('Network error saving position');
                }
            });
        }
    };

    // =========================================================================
    // 7. CATEGORIES CONTROLLER (MATCHING PIXEL-PERFECT SPECIFICATION)
    // =========================================================================
    window.NutronyxCategory = {
        openCreateModal: function() {
            const form = document.getElementById('categoryForm');
            if (form) form.reset();

            const titleEl = document.getElementById('categoryModalTitle');
            if (titleEl) titleEl.textContent = 'Create Category';
            const actionField = document.getElementById('categoryFormAction');
            if (actionField) actionField.value = 'create_category';
            const idField = document.getElementById('categoryIdField');
            if (idField) idField.value = '';
            const submitBtn = document.getElementById('categorySubmitBtn');
            if (submitBtn) submitBtn.textContent = 'Save Category';

            const preview = document.getElementById('categoryPhotoPreview');
            if (preview) preview.src = '../assets/images/about-commitment-still-life.jpg';
            const hiddenImg = document.getElementById('categoryImageUrl');
            if (hiddenImg) hiddenImg.value = 'assets/images/about-commitment-still-life.jpg';

            window.NutronyxModal.open('categoryModal');
        },

        openEdit: function(cat) {
            if (!cat) return;
            const form = document.getElementById('categoryForm');
            if (form) form.reset();

            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = (val !== null && val !== undefined) ? val : '';
            };
            const setChecked = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.checked = Boolean(val);
            };

            const titleEl = document.getElementById('categoryModalTitle');
            if (titleEl) titleEl.textContent = 'Edit Category';
            setVal('categoryFormAction', 'update_category');
            setVal('categoryIdField', cat.id || '');
            const submitBtn = document.getElementById('categorySubmitBtn');
            if (submitBtn) submitBtn.textContent = 'Save Changes';

            setVal('categoryName', cat.name || '');
            setVal('categorySlug', cat.slug || '');
            setVal('categoryDescription', cat.description || '');
            setVal('categoryStatus', (cat.status || 'ACTIVE').toUpperCase());
            setChecked('categoryIsFeatured', cat.is_featured == 1 || cat.is_featured === '1');

            let subcatsStr = '';
            if (cat.subcategories_json) {
                try {
                    const parsed = JSON.parse(cat.subcategories_json);
                    if (Array.isArray(parsed)) subcatsStr = parsed.join(', ');
                } catch(e) {}
            }
            setVal('categorySubcategories', subcatsStr);

            const imgPath = cat.image_url || 'assets/images/about-commitment-still-life.jpg';
            const fullImgPath = (imgPath.startsWith('http') || imgPath.startsWith('/')) ? imgPath : ('../' + imgPath);
            const preview = document.getElementById('categoryPhotoPreview');
            if (preview) preview.src = fullImgPath;
            setVal('categoryImageUrl', imgPath);

            window.NutronyxModal.open('categoryModal');
        },

        openEditFromRow: function(catId) {
            fetch('categories.php?ajax=1&action=get_details&category_id=' + encodeURIComponent(catId))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.category) {
                        this.openEdit(data.category);
                    } else {
                        window.NutronyxToast.error(data.error || 'Failed to load category details.');
                    }
                })
                .catch(() => {
                    window.NutronyxToast.error('Network error loading category.');
                });
        },

        toggleFeatured: function(catId, btn) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'toggle_featured');
            formData.append('category_id', catId);

            fetch('categories.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const isFeat = data.is_featured == 1;
                        const starBtns = document.querySelectorAll('.star-btn-' + catId);
                        starBtns.forEach(el => {
                            if (isFeat) {
                                el.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>`;
                                el.title = 'Featured category (click to unmark)';
                            } else {
                                el.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>`;
                                el.title = 'Click to make featured';
                            }
                        });
                        window.NutronyxToast.success(isFeat ? 'Category marked as featured.' : 'Category removed from featured.');
                    } else {
                        window.NutronyxToast.error(data.error || 'Failed to toggle featured state.');
                    }
                })
                .catch(() => {
                    window.NutronyxToast.error('Network error updating featured status.');
                });
        },

        toggleStatus: function(catId, btn) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'toggle_status');
            formData.append('category_id', catId);

            fetch('categories.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const statusPills = document.querySelectorAll('.status-pill-' + catId);
                        const isAct = (data.status === 'ACTIVE');
                        statusPills.forEach(el => {
                            el.className = 'status-pill-dot ' + (isAct ? 'active' : 'draft');
                            el.innerHTML = `<span class="dot" style="${isAct ? '' : 'background:#6B7280;'}"></span><span>${isAct ? 'Active' : 'Draft'}</span>`;
                        });
                        window.NutronyxToast.success(isAct ? 'Category activated.' : 'Category moved to draft.');
                    } else {
                        window.NutronyxToast.error(data.error || 'Failed to toggle status.');
                    }
                })
                .catch(() => {
                    window.NutronyxToast.error('Network error updating category status.');
                });
        },

        confirmDelete: function(catId, catName) {
            window.NutronyxConfirm({
                title: 'Delete Category',
                message: `Are you sure you want to permanently delete category "${catName}"? Associated products will become uncategorized.`,
                confirmText: 'Yes, Delete Category',
                cancelText: 'Cancel',
                isDanger: true,
                onConfirm: function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'categories.php';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" value="${catId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        },

        toggleSelectAll: function(cb) {
            const boxes = document.querySelectorAll('.cat-select-checkbox');
            boxes.forEach(b => b.checked = cb.checked);
            this.handleRowCheckbox();
        },

        handleRowCheckbox: function() {
            const selected = this.getSelectedIds();
            const badge = document.getElementById('catSelectedBadge');
            const countNum = document.getElementById('catSelectedCount');
            const allCheckbox = document.getElementById('selectAllCatCheckbox');
            const totalBoxes = document.querySelectorAll('.cat-select-checkbox').length;
            const bulkBtns = document.getElementById('catBulkButtonsGroup');

            if (selected.length > 0) {
                if (badge) badge.style.display = 'inline-flex';
                if (countNum) countNum.textContent = selected.length;
                if (bulkBtns) bulkBtns.style.display = 'inline-flex';
            } else {
                if (badge) badge.style.display = 'none';
                if (bulkBtns) bulkBtns.style.display = 'none';
            }

            if (allCheckbox) {
                allCheckbox.checked = (selected.length > 0 && selected.length === totalBoxes);
                allCheckbox.indeterminate = (selected.length > 0 && selected.length < totalBoxes);
            }
        },

        getSelectedIds: function() {
            const checked = document.querySelectorAll('.cat-select-checkbox:checked');
            return Array.from(checked).map(cb => cb.value);
        },

        bulkAction: function(subAction) {
            const selected = this.getSelectedIds();
            if (selected.length === 0) {
                window.NutronyxToast.warning('Please select at least one category using the checkboxes.');
                return;
            }

            if (subAction === 'delete') {
                window.NutronyxConfirm({
                    title: 'Delete Selected Categories',
                    message: `Are you sure you want to delete ${selected.length} selected category(ies)? Products will be unlinked safely.`,
                    confirmText: 'Yes, Delete',
                    cancelText: 'Cancel',
                    isDanger: true,
                    onConfirm: () => {
                        this.submitBulkForm('delete', selected);
                    }
                });
                return;
            }

            this.submitBulkForm(subAction, selected);
        },

        submitBulkForm: function(subAction, ids) {
            const form = document.getElementById('catBulkForm');
            if (!form) return;
            document.getElementById('catBulkSubactionField').value = subAction;
            document.getElementById('catBulkSelectedIdsField').value = ids.join(',');
            form.submit();
        },

        openExportModal: function() {
            const selected = this.getSelectedIds();
            const btnSelected = document.getElementById('btnExportCatSelected');
            const textSpan = document.getElementById('btnExportCatSelectedText');
            if (btnSelected) {
                btnSelected.style.display = (selected.length > 0) ? 'flex' : 'none';
                if (textSpan && selected.length > 0) {
                    textSpan.textContent = `Export Selected Checkboxes Only (${selected.length} items)`;
                }
            }
            window.NutronyxModal.open('exportCategoryModal');
        },

        exportSelected: function() {
            const selected = this.getSelectedIds();
            if (selected.length === 0) {
                window.NutronyxToast.warning('Please select at least one category checkbox to export.');
                return;
            }
            window.location.href = 'export.php?type=categories&ids=' + encodeURIComponent(selected.join(','));
            window.NutronyxModal.close('exportCategoryModal');
        },

        openImportModal: function() {
            window.NutronyxModal.open('importCategoryModal');
        },

        updatePerPage: function(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', val);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        },

        updateSort: function(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', val);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        },

        updateStatusFilter: function(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('status', val);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        },

        updateFeaturedFilter: function(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('featured', val);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        },

        switchView: function(viewMode) {
            const url = new URL(window.location.href);
            url.searchParams.set('view', viewMode);
            window.location.href = url.toString();
        },

        toggleActionMenu: function(menuId, event) {
            if (event) {
                event.stopPropagation();
            }
            const targetMenu = document.getElementById(menuId);
            const isShown = targetMenu && targetMenu.classList.contains('show');
            document.querySelectorAll('.action-dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
                m.style.top = '';
                m.style.bottom = '';
            });
            if (!isShown && targetMenu) {
                targetMenu.classList.add('show');
                const rect = targetMenu.getBoundingClientRect();
                if (rect.bottom > window.innerHeight - 20) {
                    targetMenu.style.top = 'auto';
                    targetMenu.style.bottom = 'calc(100% + 4px)';
                } else {
                    targetMenu.style.top = 'calc(100% + 4px)';
                    targetMenu.style.bottom = 'auto';
                }
            }
        },

        handleImageSelect: function(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('categoryPhotoPreview');
                    const hidden = document.getElementById('categoryImageUrl');
                    if (preview) preview.src = e.target.result;
                    if (hidden) hidden.value = e.target.result;
                    window.NutronyxToast.success('Category banner image loaded.');
                };
                reader.readAsDataURL(input.files[0]);
            }
        },

        selectPresetImage: function(path) {
            const preview = document.getElementById('categoryPhotoPreview');
            const hidden = document.getElementById('categoryImageUrl');
            if (preview) preview.src = (path.startsWith('http') || path.startsWith('/')) ? path : ('../' + path);
            if (hidden) hidden.value = path;
            window.NutronyxToast.info('Preset image selected.');
        }
    };

    // =========================================================================
    // 8. ENTERPRISE ORDERS & FULFILLMENTS CONTROLLER (MATCHING MOCKUP)
    // =========================================================================
    window.NutronyxOrder = {
        currentOrderId: null,

        loadOrderDetails: function(orderId, event) {
            if (event) event.preventDefault();
            this.currentOrderId = orderId;

            // Highlight table row
            document.querySelectorAll('.order-row-item').forEach(r => r.classList.remove('selected-order-row'));
            const activeRow = document.getElementById('row-' + orderId);
            if (activeRow) activeRow.classList.add('selected-order-row');

            const workspace = document.getElementById('orderDetailWorkspace');
            if (!workspace) return;

            workspace.style.display = 'flex';
            workspace.innerHTML = '<div style="text-align: center; padding: 60px 20px; color: #9CA3AF;"><svg class="animate-spin" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="0.75"></path></svg><div style="margin-top: 10px; font-size: 13.5px;">Loading order details...</div></div>';

            fetch('orders.php?ajax=1&action=get_order_details&order_id=' + encodeURIComponent(orderId))
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.order) {
                        workspace.innerHTML = '<div style="color: #DC2626; padding: 30px; text-align: center;">Failed to load order details.</div>';
                        return;
                    }
                    this.renderDetailsWorkspace(data.order);
                })
                .catch(() => {
                    workspace.innerHTML = '<div style="color: #DC2626; padding: 30px; text-align: center;">Network error loading order.</div>';
                });
        },

        closeDetails: function() {
            const workspace = document.getElementById('orderDetailWorkspace');
            if (workspace) workspace.style.display = 'none';
            document.querySelectorAll('.order-row-item').forEach(r => r.classList.remove('selected-order-row'));
        },

        renderDetailsWorkspace: function(o) {
            const workspace = document.getElementById('orderDetailWorkspace');
            if (!workspace) return;

            const orderDateStr = new Date(o.created_at).toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric'
            }) + ' at ' + new Date(o.created_at).toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit'
            });

            const statusClass = (o.order_status || 'PENDING').toLowerCase();
            const paymentClass = (o.payment_status || 'PENDING').toLowerCase();
            const fulfillmentClass = (o.fulfillment_status || 'PENDING').toLowerCase();

            const subtotalRupees = ((o.subtotal_amount || 0) / 100).toLocaleString('en-IN');
            const shippingRupees = ((o.shipping_amount || 0) / 100).toLocaleString('en-IN');
            const discountRupees = ((o.discount_amount || 0) / 100).toLocaleString('en-IN');
            const grandTotalRupees = ((o.grand_total_amount || 0) / 100).toLocaleString('en-IN');

            const fullAddress = `${o.shipping_address_line1 || ''}${o.shipping_address_line2 ? ', ' + o.shipping_address_line2 : ''}, ${o.shipping_city || ''}, ${o.shipping_state || ''} - ${o.shipping_postal_code || ''}`;

            let itemsHtml = '';
            if (o.items && o.items.length > 0) {
                o.items.forEach(it => {
                    const priceR = ((it.unit_price_amount || 0) / 100).toLocaleString('en-IN');
                    const totR = ((it.total_amount || 0) / 100).toLocaleString('en-IN');
                    const imgUrl = it.product_image_url ? (it.product_image_url.startsWith('http') || it.product_image_url.startsWith('/') ? it.product_image_url : '../' + it.product_image_url) : '../assets/images/about-commitment-still-life.jpg';

                    itemsHtml += `
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #F3F4F6;">
                            <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                                <div style="width: 44px; height: 44px; border-radius: 8px; border: 1px solid #E5E7EB; overflow: hidden; background: #FFF; flex-shrink: 0;">
                                    <img src="${imgUrl}" alt="${it.title}" style="width: 100%; height: 100%; object-fit: contain;">
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-weight: 600; font-size: 13px; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${it.title}</div>
                                    <div style="font-size: 11.5px; color: #6B7280; font-family: monospace;">SKU: ${it.sku || 'N/A'}</div>
                                </div>
                            </div>
                            <div style="text-align: right; flex-shrink: 0; padding-left: 10px;">
                                <div style="font-size: 12.5px; color: #4B5563;">₹${priceR} &times; ${it.quantity}</div>
                                <div style="font-size: 13.5px; font-weight: 700; color: #111827;">₹${totR}</div>
                            </div>
                        </div>
                    `;
                });
            } else {
                itemsHtml = '<div style="color: #9CA3AF; padding: 10px 0; font-size: 12px;">No item records found.</div>';
            }

            let timelineHtml = '';
            if (o.timeline && o.timeline.length > 0) {
                o.timeline.forEach(tl => {
                    const dtStr = new Date(tl.created_at).toLocaleDateString('en-US', {
                        month: 'short', day: 'numeric', year: 'numeric'
                    }) + ', ' + new Date(tl.created_at).toLocaleTimeString('en-US', {
                        hour: '2-digit', minute: '2-digit'
                    });

                    timelineHtml += `
                        <div class="timeline-v2-item">
                            <span class="node-dot"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 13px; color: #111827;">${tl.event_type.replace(/_/g, ' ')}</div>
                                    <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">${tl.message}</div>
                                </div>
                                <span style="font-size: 11px; color: #9CA3AF; white-space: nowrap;">${dtStr}</span>
                            </div>
                        </div>
                    `;
                });
            } else {
                timelineHtml = '<div style="color: #9CA3AF; font-size: 12px; padding: 8px 0;">No events logged yet.</div>';
            }

            let shipmentHtml = '';
            if (o.shipments && o.shipments.length > 0) {
                o.shipments.forEach(s => {
                    const sCarrier = s.carrier || 'Ekart Logistics';
                    let trackingUrl = s.tracking_url;
                    if (!trackingUrl && s.tracking_number) {
                        if (sCarrier.toLowerCase().includes('ekart')) {
                            trackingUrl = 'https://app.elite.ekartlogistics.in/track/' + encodeURIComponent(s.tracking_number);
                        } else if (sCarrier.toLowerCase().includes('delhivery')) {
                            trackingUrl = 'https://www.delhivery.com/track/package/' + encodeURIComponent(s.tracking_number);
                        }
                    }
                    shipmentHtml += `
                        <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <span style="font-weight: 700; font-size: 13px; color: #111827;">${sCarrier}</span>
                                <span class="status-pill-dot ${s.status ? s.status.toLowerCase() : 'shipped'}"><span class="dot"></span><span>${s.status || 'SHIPPED'}</span></span>
                            </div>
                            <div style="font-size: 12px; color: #4B5563; margin-bottom: 6px;">
                                Tracking AWB: <strong style="color: #111827; font-family: monospace;">${s.tracking_number}</strong>
                            </div>
                            <div style="margin-bottom: 6px;">
                                <a href="${trackingUrl || '#'}" target="_blank" rel="noopener noreferrer" style="color: #047857; font-weight: 600; text-decoration: underline; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                    <span>Track on ${sCarrier} &rarr;</span>
                                </a>
                            </div>
                            <div style="font-size: 11px; color: #9CA3AF;">
                                Shipped: ${s.shipped_at || 'Just now'} &middot; Delivered: ${s.delivered_at || 'Pending'}
                            </div>
                        </div>
                    `;
                });
            } else {
                shipmentHtml = `
                    <div style="text-align: center; padding: 20px 10px; background: #F9FAFB; border-radius: 8px; border: 1px dashed #D1D5DB;">
                        <div style="font-size: 12.5px; color: #6B7280; margin-bottom: 10px;">No active shipments created for this order.</div>
                        <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                            <button type="button" class="btn-emerald-brand" style="font-size: 12px; padding: 7px 14px; background: #047857;" onclick="window.NutronyxOrder.openEkartShipModal('${o.id}', '${o.order_number}', '${(o.shipping_name || o.customer_name || '').replace(/'/g, "\\'")}', '${(o.shipping_phone || o.customer_phone || '').replace(/'/g, "\\'")}', '${fullAddress.replace(/'/g, "\\'")}', ${(o.grand_total_amount || 0)/100}, '${o.payment_method || 'Prepaid'}')">Ship on Ekart</button>
                        </div>
                    </div>
                `;
            }

            workspace.innerHTML = `
                <!-- Workspace Header -->
                <div class="order-workspace-header">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h2 class="order-heading-title">#${o.order_number}</h2>
                            <button type="button" class="star-toggle-btn" title="Copy Order Number" onclick="window.NutronyxOrder.copyText('${o.order_number}', 'Order Number')" style="padding: 2px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            </button>
                        </div>
                        <div style="font-size: 12px; color: #6B7280; margin-top: 3px;">Placed on ${orderDateStr}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="status-pill-dot ${fulfillmentClass}">
                            <span class="dot"></span>
                            <span>${o.fulfillment_status}</span>
                        </span>
                        <button type="button" onclick="window.NutronyxOrder.closeDetails()" style="border: none; background: transparent; cursor: pointer; color: #9CA3AF; padding: 4px; font-size: 18px; line-height: 1;">&times;</button>
                    </div>
                </div>

                <!-- Full-Width Summary Tiles Banner: Payment, Fulfillment, Order Type -->
                <div class="order-summary-tiles-strip">
                    <div class="summary-tile-box">
                        <div class="tile-header">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                            <span>Payment</span>
                        </div>
                        <div class="tile-status-pill ${paymentClass}">
                            <span class="dot"></span>
                            <span>${(o.payment_status || 'PAID').toUpperCase()}</span>
                        </div>
                        <div class="tile-subtext" title="${o.payment_method || 'Online'}">
                            ${o.payment_method || 'Online (Razorpay UPI)'}
                        </div>
                    </div>

                    <div class="summary-tile-box">
                        <div class="tile-header">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            <span>Fulfillment</span>
                        </div>
                        <div class="tile-status-pill ${fulfillmentClass}">
                            <span class="dot"></span>
                            <span>${(o.fulfillment_status || 'DELIVERED').toUpperCase()}</span>
                        </div>
                        <div class="tile-subtext" title="${(o.shipments && o.shipments[0] && (o.shipments[0].carrier_name || o.shipments[0].carrier)) || 'Ekart Logistics Enterprise'}">
                            ${(o.shipments && o.shipments[0] && (o.shipments[0].carrier_name || o.shipments[0].carrier)) || 'Ekart Logistics Enterprise'}
                        </div>
                    </div>

                    <div class="summary-tile-box">
                        <div class="tile-header">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                            <span>Order Type</span>
                        </div>
                        <div class="tile-status-pill online">
                            <span class="dot"></span>
                            <span>ONLINE</span>
                        </div>
                        <div class="tile-subtext" title="Direct Storefront">
                            Direct Storefront
                        </div>
                    </div>
                </div>

                <!-- 2-Column Body Grid: Left Sub-Column & Right Sub-Column -->
                <div style="display: grid; grid-template-columns: 1fr 140px; gap: 16px; margin-bottom: 20px;">
                    
                    <!-- Left Sub-Column: Customer, Address, Items, Totals -->
                    <div style="display: flex; flex-direction: column; gap: 14px;">

                        <!-- Customer Details Card -->
                        <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 10px 12px;">
                            <div style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <span>Customer</span>
                            </div>
                            <div style="font-weight: 700; font-size: 13px; color: #111827;">${o.shipping_name || o.customer_name || 'Walk-in Customer'}</div>
                            <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
                                📞 ${o.shipping_phone || o.customer_phone || 'N/A'}
                            </div>
                            ${o.customer_email ? `<div style="font-size: 11.5px; color: #6B7280; margin-top: 2px;">✉ ${o.customer_email}</div>` : ''}
                        </div>

                        <!-- Shipping Address Card -->
                        <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 10px 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <div style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 4px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    <span>Shipping Address</span>
                                </div>
                                <button type="button" class="star-toggle-btn" title="Copy Address" onclick="window.NutronyxOrder.copyText('${fullAddress.replace(/'/g, "\\'")}', 'Address')" style="padding: 2px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                </button>
                            </div>
                            <div style="font-size: 12px; color: #374151; line-height: 1.45;">${fullAddress}</div>
                            <div style="margin-top: 4px;">
                                <a href="javascript:void(0)" onclick="window.NutronyxOrder.openAddressModal('${o.id}')" style="font-size: 11.5px; color: #144D29; text-decoration: underline; font-weight: 600;">Edit Address</a>
                            </div>
                        </div>

                        <!-- Items in Order -->
                        <div>
                            <div style="font-size: 11.5px; font-weight: 700; color: #4B5563; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; display: flex; align-items: center; gap: 4px;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                <span>Order Items (${o.items ? o.items.length : 0})</span>
                            </div>
                            <div style="border-radius: 8px; border: 1px solid #E5E7EB; background: #FFF; padding: 2px 10px;">
                                ${itemsHtml}
                            </div>
                        </div>

                        <!-- Financial Breakdown & Total Paid Green Banner -->
                        <div>
                            <div style="font-size: 12.5px; color: #4B5563; display: flex; flex-direction: column; gap: 4px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Subtotal</span>
                                    <span style="font-weight: 600; color: #111827;">₹${subtotalRupees}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Shipping</span>
                                    <span style="font-weight: 600; color: #111827;">${(o.shipping_amount === 0) ? 'Free' : ('₹' + shippingRupees)}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Discount</span>
                                    <span style="color: #6B7280;">- ₹${discountRupees}</span>
                                </div>
                            </div>

                            <div class="total-paid-banner">
                                <span class="banner-label">Total Paid</span>
                                <span class="banner-amount">₹${grandTotalRupees}</span>
                            </div>
                        </div>

                    </div>

                    <!-- Right Sub-Column: Vertical Stack of 7 Action Buttons -->
                    <div class="order-action-vertical-stack">
                        <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.directPrintInvoice('${o.id}')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            <span>Print Invoice</span>
                        </button>

                        <a href="receipt.php?id=${encodeURIComponent(o.id)}&download=pdf" download="Nutronyx-Receipt-${encodeURIComponent(o.order_number || o.id)}.pdf" target="_blank" class="btn-order-action-card">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            <span>Download Receipt</span>
                        </a>

                        <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.openEkartShipModal('${o.id}', '${o.order_number}', '${(o.shipping_name || o.customer_name || '').replace(/'/g, "\\'")}', '${(o.shipping_phone || o.customer_phone || '').replace(/'/g, "\\'")}', '${fullAddress.replace(/'/g, "\\'")}', ${(o.grand_total_amount || 0)/100}, '${o.payment_method || 'Prepaid'}')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            <span>Ship on Ekart</span>
                        </button>

                        <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.openRefundModal('${o.id}', ${(o.refundable_balance || o.grand_total_amount || 0)/100})">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                            <span>Process Refund</span>
                        </button>

                        <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.openReturnModal('${o.id}')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                            <span>Create Return</span>
                        </button>

                        <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.resendConfirmation('${o.id}')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <span>Resend Confirm</span>
                        </button>

                        <button type="button" class="btn-order-action-card" onclick="window.NutronyxOrder.openNotesModal('${o.id}', '${(o.internal_notes || '').replace(/'/g, "\\'")}')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            <span>Order Notes</span>
                        </button>
                    </div>

                </div>

                <!-- Full-Width Bottom Section: Tabs & Interactive Timeline -->
                <div style="border-top: 1px solid #E5E7EB; padding-top: 16px;">
                    <div style="display: flex; gap: 16px; border-bottom: 1px solid #E5E7EB; margin-bottom: 14px;">
                        <button type="button" id="tabBtnActivity" class="order-tab-btn active" style="padding-bottom: 8px; font-size: 13px;" onclick="window.NutronyxOrder.switchDetailTab('activity')">Order Activity</button>
                        <button type="button" id="tabBtnTracking" class="order-tab-btn" style="padding-bottom: 8px; font-size: 13px;" onclick="window.NutronyxOrder.switchDetailTab('tracking')">Shipment Tracking</button>
                    </div>

                    <div id="detailTabActivity">
                        <div class="order-timeline-v2">
                            ${timelineHtml}
                        </div>
                    </div>

                    <div id="detailTabTracking" style="display: none;">
                        ${shipmentHtml}
                    </div>
                </div>
            `;
        },

        switchDetailTab: function(tabName) {
            const btnAct = document.getElementById('tabBtnActivity');
            const btnTrk = document.getElementById('tabBtnTracking');
            const paneAct = document.getElementById('detailTabActivity');
            const paneTrk = document.getElementById('detailTabTracking');

            if (tabName === 'activity') {
                if (btnAct) btnAct.classList.add('active');
                if (btnTrk) btnTrk.classList.remove('active');
                if (paneAct) paneAct.style.display = 'block';
                if (paneTrk) paneTrk.style.display = 'none';
            } else {
                if (btnAct) btnAct.classList.remove('active');
                if (btnTrk) btnTrk.classList.add('active');
                if (paneAct) paneAct.style.display = 'none';
                if (paneTrk) paneTrk.style.display = 'block';
            }
        },

        openCreateOrderModal: function() {
            window.NutronyxModal.open('createOrderModal');
        },

        openShipModal: function(orderId, carrier, tracking) {
            document.getElementById('shipOrderIdField').value = orderId;
            document.getElementById('shipCarrierInput').value = carrier || 'Ekart Logistics';
            document.getElementById('shipTrackingInput').value = tracking || '';
            window.NutronyxModal.open('shipOrderModal');
        },

        openEkartShipModal: function(orderId, orderNum, name, phone, address, totalRupees, payMode) {
            const idField = document.getElementById('ekartShipOrderId');
            if (idField) idField.value = orderId;

            const subTitle = document.getElementById('ekartModalOrderSubtitle');
            if (subTitle && orderNum) subTitle.textContent = `Order #${orderNum} • Divert to Ekart Portal & Enter Authentic AWB`;

            const numSpan = document.getElementById('ekartFieldOrderNum');
            if (numSpan && orderNum) numSpan.textContent = orderNum;

            const nameSpan = document.getElementById('ekartFieldName');
            if (nameSpan && name) nameSpan.textContent = name;

            const phoneSpan = document.getElementById('ekartFieldPhone');
            if (phoneSpan && phone) phoneSpan.textContent = phone;

            const addrSpan = document.getElementById('ekartFieldAddress');
            if (addrSpan && address) addrSpan.textContent = address;

            const amtSpan = document.getElementById('ekartFieldAmount');
            if (amtSpan && totalRupees !== undefined) amtSpan.textContent = (parseFloat(totalRupees) || 0).toLocaleString('en-IN');

            const modeSpan = document.getElementById('ekartFieldPayMode');
            if (modeSpan && payMode) modeSpan.textContent = payMode;

            const awbInput = document.getElementById('ekartAwbInput');
            if (awbInput) {
                awbInput.value = '';
                this.previewEkartTrackingUrl('');
            }

            this._currentEkartShippingText = `Order: ${orderNum || orderId}\nCustomer: ${name || ''}\nPhone: ${phone || ''}\nAddress: ${address || ''}\nAmount: ₹${totalRupees || ''}\nPayment: ${payMode || 'Prepaid'}`;

            if (!name || !address) {
                fetch('orders.php?ajax=1&action=get_order_details&order_id=' + encodeURIComponent(orderId))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.order) {
                            const o = data.order;
                            const ordNum = o.order_number || ('#' + o.id);
                            const custName = o.shipping_name || o.customer_name || 'Customer';
                            const custPhone = o.shipping_phone || o.customer_phone || '';
                            const fullAddr = `${o.shipping_address_line1 || ''}${o.shipping_address_line2 ? ', ' + o.shipping_address_line2 : ''}, ${o.shipping_city || ''}, ${o.shipping_state || ''} - ${o.shipping_postal_code || ''}`;
                            const tot = (parseFloat(o.grand_total_amount) || 0) / 100;
                            const mode = (o.payment_method && o.payment_method.toUpperCase() === 'COD') ? 'COD' : 'Prepaid';

                            if (subTitle) subTitle.textContent = `Order #${ordNum} • Divert to Ekart Portal & Enter Authentic AWB`;
                            if (numSpan) numSpan.textContent = ordNum;
                            if (nameSpan) nameSpan.textContent = custName;
                            if (phoneSpan) phoneSpan.textContent = custPhone;
                            if (addrSpan) addrSpan.textContent = fullAddr;
                            if (amtSpan) amtSpan.textContent = tot.toLocaleString('en-IN');
                            if (modeSpan) modeSpan.textContent = mode;

                            this._currentEkartShippingText = `Order: ${ordNum}\nCustomer: ${custName}\nPhone: ${custPhone}\nAddress: ${fullAddr}\nAmount: ₹${tot}\nPayment: ${mode}`;
                        }
                    })
                    .catch(() => {});
            }

            window.NutronyxModal.open('ekartShipModal');

            try {
                const ekartWin = window.open('https://app.elite.ekartlogistics.in/', '_blank');
                if (ekartWin) ekartWin.focus();
            } catch (e) {}
        },

        copyEkartShippingInfo: function() {
            const text = this._currentEkartShippingText || '';
            if (!text) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    const btnText = document.getElementById('copyEkartDetailsText');
                    if (btnText) {
                        const orig = btnText.textContent;
                        btnText.textContent = 'Copied!';
                        setTimeout(() => { btnText.textContent = orig; }, 2000);
                    }
                    if (window.NutronyxToast) window.NutronyxToast.success('Consignee details copied for Ekart!');
                });
            } else {
                window.NutronyxOrder.copyText(text, 'Consignee Details');
            }
        },

        previewEkartTrackingUrl: function(awb) {
            const box = document.getElementById('ekartTrackingPreviewBox');
            const link = document.getElementById('ekartTrackingPreviewLink');
            if (!box || !link) return;
            const clean = (awb || '').trim();
            if (clean.length > 0) {
                const url = 'https://app.elite.ekartlogistics.in/track/' + encodeURIComponent(clean);
                link.href = url;
                link.textContent = url;
                box.style.display = 'block';
            } else {
                box.style.display = 'none';
            }
        },

        openRefundModal: function(orderId, maxRefundRupees) {
            document.getElementById('refundOrderIdField').value = orderId;
            document.getElementById('refundMaxBalanceNotice').textContent = 'Max refundable: ₹' + (maxRefundRupees || 0).toLocaleString('en-IN');
            document.getElementById('refundAmountInput').value = maxRefundRupees || '';
            window.NutronyxModal.open('refundOrderModal');
        },

        openReturnModal: function(orderId) {
            document.getElementById('returnOrderIdField').value = orderId;
            window.NutronyxModal.open('returnOrderModal');
        },

        openNotesModal: function(orderId, notes) {
            document.getElementById('noteOrderIdField').value = orderId;
            document.getElementById('existingNotesDisplay').textContent = notes || 'No notes logged yet.';
            document.getElementById('newNoteInput').value = '';
            window.NutronyxModal.open('orderNotesModal');
        },

        openAddressModal: function(orderId) {
            fetch('orders.php?ajax=1&action=get_order_details&order_id=' + encodeURIComponent(orderId))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.order) {
                        const o = data.order;
                        document.getElementById('addrOrderIdField').value = o.id;
                        document.getElementById('addrNameInput').value = o.shipping_name || '';
                        document.getElementById('addrPhoneInput').value = o.shipping_phone || '';
                        document.getElementById('addrLine1Input').value = o.shipping_address_line1 || '';
                        document.getElementById('addrLine2Input').value = o.shipping_address_line2 || '';
                        document.getElementById('addrCityInput').value = o.shipping_city || '';
                        document.getElementById('addrStateInput').value = o.shipping_state || '';
                        document.getElementById('addrPincodeInput').value = o.shipping_postal_code || '';
                        window.NutronyxModal.open('orderAddressModal');
                    }
                });
        },

        markDelivered: function(orderId) {
            window.NutronyxConfirm({
                title: 'Mark Order Delivered',
                message: 'Confirm package delivery to the recipient? This will update fulfillment status.',
                confirmText: 'Yes, Confirm Delivery',
                onConfirm: () => {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'mark_delivered');
                    formData.append('order_id', orderId);
                    fetch('orders.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                window.NutronyxToast.success('Order marked as delivered.');
                                this.loadOrderDetails(orderId);
                            } else {
                                window.NutronyxToast.error(data.error || 'Failed to update delivery.');
                            }
                        });
                }
            });
        },

        resendConfirmation: function(orderId) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'resend_confirmation');
            formData.append('order_id', orderId);
            fetch('orders.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.NutronyxToast.success('Order confirmation resent to customer.');
                        if (this.currentOrderId === orderId) this.loadOrderDetails(orderId);
                    } else {
                        window.NutronyxToast.error(data.error || 'Failed to resend confirmation.');
                    }
                });
        },

        toggleSelectAll: function(cb) {
            const boxes = document.querySelectorAll('.order-select-checkbox');
            boxes.forEach(b => b.checked = cb.checked);
            this.handleRowCheckbox();
        },

        handleRowCheckbox: function() {
            const selected = this.getSelectedIds();
            const badge = document.getElementById('orderSelectedBadge');
            const countNum = document.getElementById('orderSelectedCount');
            const allCheckbox = document.getElementById('selectAllOrderCheckbox');
            const totalBoxes = document.querySelectorAll('.order-select-checkbox').length;
            const bulkBtns = document.getElementById('orderBulkButtonsGroup');

            if (selected.length > 0) {
                if (badge) badge.style.display = 'inline-flex';
                if (countNum) countNum.textContent = selected.length;
                if (bulkBtns) bulkBtns.style.display = 'inline-flex';
            } else {
                if (badge) badge.style.display = 'none';
                if (bulkBtns) bulkBtns.style.display = 'none';
            }

            if (allCheckbox) {
                allCheckbox.checked = (selected.length > 0 && selected.length === totalBoxes);
                allCheckbox.indeterminate = (selected.length > 0 && selected.length < totalBoxes);
            }
        },

        getSelectedIds: function() {
            const checked = document.querySelectorAll('.order-select-checkbox:checked');
            return Array.from(checked).map(cb => cb.value);
        },

        bulkAction: function(subAction) {
            const selected = this.getSelectedIds();
            if (selected.length === 0) {
                window.NutronyxToast.warning('Please select at least one order using the checkboxes.');
                return;
            }

            if (subAction === 'cancel') {
                window.NutronyxConfirm({
                    title: 'Cancel Selected Orders',
                    message: `Are you sure you want to cancel ${selected.length} order(s)? Allocated inventory will be returned to stock.`,
                    confirmText: 'Yes, Cancel Orders',
                    isDanger: true,
                    onConfirm: () => {
                        this.submitBulkForm('cancel_orders', selected);
                    }
                });
                return;
            }

            if (subAction === 'print_invoice') {
                this.directPrintBulkInvoices(selected);
                return;
            }

            this.submitBulkForm(subAction, selected);
        },

        submitBulkForm: function(subAction, ids) {
            const form = document.getElementById('orderBulkForm');
            if (!form) return;
            document.getElementById('orderBulkSubactionField').value = subAction;
            document.getElementById('orderBulkSelectedIdsField').value = ids.join(',');
            form.submit();
        },

        openExportModal: function() {
            const selected = this.getSelectedIds();
            const btnSelected = document.getElementById('btnExportOrderSelected');
            const textSpan = document.getElementById('btnExportOrderSelectedText');
            if (btnSelected) {
                btnSelected.style.display = (selected.length > 0) ? 'flex' : 'none';
                if (textSpan && selected.length > 0) {
                    textSpan.textContent = `Export Selected Checkboxes Only (${selected.length} orders)`;
                }
            }
            window.NutronyxModal.open('exportOrderModal');
        },

        exportSelected: function() {
            const selected = this.getSelectedIds();
            if (selected.length === 0) {
                window.NutronyxToast.warning('Please select at least one order to export.');
                return;
            }
            window.location.href = 'export.php?type=orders&ids=' + encodeURIComponent(selected.join(','));
            window.NutronyxModal.close('exportOrderModal');
        },

        openImportModal: function() {
            window.NutronyxModal.open('importOrderModal');
        },

        toggleMoreFilters: function() {
            const p = document.getElementById('orderMoreFiltersPanel');
            if (p) p.style.display = (p.style.display === 'none' || p.style.display === '') ? 'block' : 'none';
        },

        toggleActionMenu: function(menuId, event) {
            if (event) event.stopPropagation();
            const targetMenu = document.getElementById(menuId);
            const isShown = targetMenu && targetMenu.classList.contains('show');
            document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
            if (!isShown && targetMenu) {
                targetMenu.classList.add('show');
            }
        },

        copyText: function(text, label) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    window.NutronyxToast.success(label + ' copied to clipboard!');
                });
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                window.NutronyxToast.success(label + ' copied to clipboard!');
            }
        },

        directPrintInvoice: function(orderId) {
            if (!orderId) return;
            if (window.NutronyxToast) window.NutronyxToast.info('Opening direct print dialog...');

            let iframe = document.getElementById('nutronyxDirectPrintFrame');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'nutronyxDirectPrintFrame';
                iframe.name = 'nutronyxDirectPrintFrame';
                iframe.style.position = 'fixed';
                iframe.style.left = '-9999px';
                iframe.style.top = '-9999px';
                iframe.style.width = '1px';
                iframe.style.height = '1px';
                iframe.style.opacity = '0.01';
                iframe.style.pointerEvents = 'none';
                iframe.style.border = '0';
                document.body.appendChild(iframe);
            }

            iframe.src = 'invoice.php?id=' + encodeURIComponent(orderId) + '&noprint=1';

            iframe.onload = function() {
                setTimeout(function() {
                    try {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                    } catch (err) {
                        console.warn('Direct iframe print blocked, falling back to window:', err);
                        window.open('invoice.php?id=' + encodeURIComponent(orderId), '_blank');
                    }
                }, 200);
            };
        },

        directPrintBulkInvoices: function(ids) {
            if (!ids || ids.length === 0) return;
            if (window.NutronyxToast) window.NutronyxToast.info(`Opening direct print dialog for ${ids.length} order(s)...`);

            let iframe = document.getElementById('nutronyxDirectPrintFrame');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'nutronyxDirectPrintFrame';
                iframe.name = 'nutronyxDirectPrintFrame';
                iframe.style.position = 'fixed';
                iframe.style.left = '-9999px';
                iframe.style.top = '-9999px';
                iframe.style.width = '1px';
                iframe.style.height = '1px';
                iframe.style.opacity = '0.01';
                iframe.style.pointerEvents = 'none';
                iframe.style.border = '0';
                document.body.appendChild(iframe);
            }

            iframe.src = 'invoice.php?ids=' + encodeURIComponent(ids.join(',')) + '&noprint=1';

            iframe.onload = function() {
                setTimeout(function() {
                    try {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                    } catch (err) {
                        console.warn('Direct bulk print blocked, falling back to window:', err);
                        window.open('invoice.php?ids=' + encodeURIComponent(ids.join(',')), '_blank');
                    }
                }, 200);
            };
        }
    };

    // --- 9. AUTO-INIT ON DOM READY ---
    function initAll() {
        initSidebar();
        if (window.NutronyxOrder && document.querySelector('.order-select-checkbox')) {
            window.NutronyxOrder.handleRowCheckbox();
        }

        // Auto-dismiss alert notifications after 15 seconds
        document.querySelectorAll('.auto-dismiss-alert, .dash-panel[style*="ECFDF5"], .dash-panel[style*="FEF2F2"]').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'opacity 0.6s ease, max-height 0.6s ease, margin 0.6s ease, padding 0.6s ease';
                el.style.opacity = '0';
                el.style.overflow = 'hidden';
                setTimeout(() => {
                    el.style.maxHeight = '0px';
                    el.style.paddingTop = '0px';
                    el.style.paddingBottom = '0px';
                    el.style.marginTop = '0px';
                    el.style.marginBottom = '0px';
                    setTimeout(() => el.remove(), 600);
                }, 300);
            }, 15000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
