<!-- EKART LOGISTICS DIRECT DISPATCH & DIVERT MODAL -->
<div id="ekartShipModal" class="modal-overlay">
    <div class="modal-card" style="max-width: 580px; border-radius: 14px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
        <!-- Modal Header -->
        <div class="modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 100%); color: #FFF; padding: 20px 24px; border: none;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                </div>
                <div>
                    <h3 class="modal-title" style="color: #FFF; margin: 0; font-size: 17px; font-weight: 700; letter-spacing: -0.01em;">Ship on Ekart Logistics</h3>
                    <div style="font-size: 12.5px; color: #A7F3D0; margin-top: 2px;" id="ekartModalOrderSubtitle">Book on Ekart Portal &bull; Enter Official AWB</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" style="color: #FFF; opacity: 0.85; font-size: 24px;" onclick="window.NutronyxModal.close('ekartShipModal')">&times;</button>
        </div>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'orders.php') ?>">
            <input type="hidden" name="action" value="ship_ekart">
            <input type="hidden" name="order_id" id="ekartShipOrderId" value="">

            <div class="modal-body" style="padding: 22px 24px; max-height: calc(85vh - 130px); overflow-y: auto;">
                
                <!-- Step 1: Divert to Ekart Portal -->
                <div style="background: #F0FDF4; border: 1.5px solid #BBF7D0; border-radius: 10px; padding: 14px 16px; margin-bottom: 18px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div>
                            <div style="font-weight: 700; font-size: 13.5px; color: #065F46; display: flex; align-items: center; gap: 6px;">
                                <span style="background: #047857; color: #FFF; width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">1</span>
                                <span>Ekart Logistics Merchant Portal</span>
                            </div>
                            <div style="font-size: 12px; color: #047857; margin-top: 4px; line-height: 1.4;">
                                Open Ekart to book this parcel and receive the official tracking AWB number.
                            </div>
                        </div>
                        <a href="https://app.elite.ekartlogistics.in/" target="_blank" rel="noopener noreferrer" class="btn-emerald-brand" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 12.5px; background: #047857; color: #FFF; border-radius: 6px; font-weight: 600; white-space: nowrap; box-shadow: 0 1px 3px rgba(4, 120, 87, 0.3);">
                            <span>Open Ekart Portal</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                    </div>
                </div>

                <!-- Consignee Details & 1-Click Copy Box -->
                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #4B5563; letter-spacing: 0.5px;">Consignee &amp; Delivery Details</span>
                        <button type="button" id="btnCopyEkartDetails" class="btn-action btn-action-sm btn-action-outline" style="font-size: 11.5px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border-radius: 6px;" onclick="window.NutronyxOrder.copyEkartShippingInfo()">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <span id="copyEkartDetailsText">Copy for Ekart</span>
                        </button>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; font-size: 12.5px; color: #1F2937;">
                        <div>
                            <span style="color: #6B7280; font-size: 11.5px; display: block;">Order Reference</span>
                            <strong id="ekartFieldOrderNum">-</strong> (<span id="ekartFieldPayMode" style="font-weight: 600; color: #047857;">-</span>)
                        </div>
                        <div>
                            <span style="color: #6B7280; font-size: 11.5px; display: block;">Package Value</span>
                            <strong>₹<span id="ekartFieldAmount">-</span></strong>
                        </div>
                        <div>
                            <span style="color: #6B7280; font-size: 11.5px; display: block;">Customer Name</span>
                            <strong id="ekartFieldName">-</strong>
                        </div>
                        <div>
                            <span style="color: #6B7280; font-size: 11.5px; display: block;">Phone Number</span>
                            <strong id="ekartFieldPhone">-</strong>
                        </div>
                        <div style="grid-column: span 2;">
                            <span style="color: #6B7280; font-size: 11.5px; display: block;">Destination Address</span>
                            <span id="ekartFieldAddress" style="font-weight: 500; line-height: 1.4;">-</span>
                        </div>
                    </div>
                </div>

                <!-- Option A: 1-Click Auto-Ship via Ekart API -->
                <div style="background: #F0FDF4; border: 1.5px solid #10B981; border-radius: 10px; padding: 16px 18px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
                        <div>
                            <div style="font-weight: 700; font-size: 14px; color: #065F46; display: flex; align-items: center; gap: 6px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>Automatic 1-Click Booking via Ekart API</span>
                            </div>
                            <div style="font-size: 12px; color: #047857; margin-top: 4px; line-height: 1.4;">
                                Directly creates package on Ekart Logistics using registered warehouse origin and fetches official AWB.
                            </div>
                        </div>
                        <button type="submit" name="action" value="ship_ekart_auto" onclick="document.getElementById('ekartAwbInput').removeAttribute('required');" class="btn-emerald-brand" style="padding: 9px 18px; background: #047857; color: #FFF; border: none; border-radius: 6px; font-weight: 700; font-size: 12.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(4, 120, 87, 0.3);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polyline></svg>
                            <span>Auto-Book on Ekart API</span>
                        </button>
                    </div>
                </div>

                <div style="text-align: center; margin: 12px 0 16px; position: relative;">
                    <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #E5E7EB;"></div>
                    <span style="position: relative; background: #FFF; padding: 0 12px; font-size: 11.5px; color: #9CA3AF; font-weight: 600; text-transform: uppercase;">Or manual booking via portal</span>
                </div>

                <!-- Option B: Enter Official Ekart AWB manually -->
                <div>
                    <label class="form-label" style="display: block; font-size: 13px; font-weight: 700; color: #111827; margin-bottom: 6px;">
                        Enter Authentic Ekart AWB / Tracking #
                    </label>
                    <input type="text" name="tracking_number" id="ekartAwbInput" class="form-control" placeholder="e.g. 500999A1084920 or official Ekart AWB" style="width: 100%; padding: 11px 14px; border: 1.5px solid #D1D5DB; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: monospace; letter-spacing: 0.5px;" oninput="window.NutronyxOrder.previewEkartTrackingUrl(this.value)">
                    
                    <div style="font-size: 11.5px; color: #4B5563; margin-top: 6px; display: flex; align-items: center; gap: 6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <span>If booked on portal, paste AWB here to dispatch and send tracking link to customer.</span>
                    </div>

                    <!-- Live URL preview -->
                    <div id="ekartTrackingPreviewBox" style="display: none; margin-top: 10px; background: #F3F4F6; padding: 10px 14px; border-radius: 8px; font-size: 12px; color: #374151; border: 1px dashed #D1D5DB;">
                        <div style="font-weight: 600; color: #4B5563; margin-bottom: 2px;">Official Ekart Tracking URL:</div>
                        <a id="ekartTrackingPreviewLink" href="#" target="_blank" rel="noopener noreferrer" style="color: #047857; font-weight: 600; word-break: break-all; display: inline-flex; align-items: center; gap: 4px; text-decoration: underline;"></a>
                    </div>
                </div>

            </div>

            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #E5E7EB; display: flex; justify-content: flex-end; gap: 10px; background: #FAFAFA; border-radius: 0 0 14px 14px;">
                <button type="button" class="btn-action btn-action-outline" onclick="window.NutronyxModal.close('ekartShipModal')">Cancel</button>
                <button type="submit" name="action" value="ship_ekart" class="btn-emerald-brand" style="padding: 9px 22px; border: none; background: #111827; color: #FFF; border-radius: 6px; font-weight: 600; font-size: 13.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Save Manual AWB &amp; Dispatch</span>
                </button>
            </div>
        </form>
    </div>
</div>
