// Admin Deals Management JavaScript

class AdminDealsManager {
    constructor() {
        this.currentDealId = null;
        this.init();
    }

    init() {
        this.loadDeals();
        this.loadStats();
        this.bindEvents();
        
        // Refresh data every 30 seconds
        setInterval(() => {
            this.loadDeals();
            this.loadStats();
        }, 30000);
    }

    bindEvents() {
        // Modal events
        document.getElementById('closeModal').addEventListener('click', () => {
            this.closeModal();
        });
        
        document.getElementById('cancelModal').addEventListener('click', () => {
            this.closeModal();
        });
        
        document.getElementById('approveDeal').addEventListener('click', () => {
            this.approveDeal();
        });
        
        document.getElementById('rejectDeal').addEventListener('click', () => {
            this.showRejectModal();
        });
        
        // Reject modal events
        document.getElementById('confirmReject').addEventListener('click', () => {
            this.confirmReject();
        });
        
        document.getElementById('cancelReject').addEventListener('click', () => {
            this.hideRejectModal();
        });
        
        // Close modals when clicking outside
        document.getElementById('dealModal').addEventListener('click', (e) => {
            if (e.target.id === 'dealModal') {
                this.closeModal();
            }
        });
        
        document.getElementById('rejectModal').addEventListener('click', (e) => {
            if (e.target.id === 'rejectModal') {
                this.hideRejectModal();
            }
        });
    }

    async loadStats() {
        try {
            const response = await fetch('api/admin_deals.php?action=stats');
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('pendingCount').textContent = data.stats.pending_count;
                document.getElementById('completedToday').textContent = data.stats.completed_today;
                document.getElementById('totalEscrow').textContent = data.stats.total_escrow + ' ج.م';
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async loadDeals() {
        try {
            this.showLoading();
            const response = await fetch('api/admin_deals.php?action=pending');
            const data = await response.json();
            
            if (data.success) {
                this.renderDeals(data.deals);
            } else {
                this.showError('فشل في تحميل الصفقات: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading deals:', error);
            this.showError('حدث خطأ في تحميل الصفقات');
        } finally {
            this.hideLoading();
        }
    }

    renderDeals(deals) {
        const tbody = document.getElementById('dealsTableBody');
        const noDeals = document.getElementById('noDeals');
        
        if (deals.length === 0) {
            tbody.innerHTML = '';
            noDeals.classList.remove('hidden');
            this.updateStats(deals);
            return;
        }
        
        noDeals.classList.add('hidden');
        
        // تحديث الإحصائيات
        this.updateStats(deals);
        
        tbody.innerHTML = deals.map(deal => {
            // حساب مبلغ الربح (10% من المبلغ الإجمالي)
            const totalAmount = parseFloat(deal.amount) || 0;
            const profitAmount = totalAmount * 0.10; // دائماً 10%
            const sellerAmount = totalAmount - profitAmount;
            
            return `
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="adminDeals.viewDeal(${deal.id})">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <span class="font-mono text-blue-600">#${deal.id}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${deal.buyer_name}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${deal.seller_name}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${deal.game_name || deal.account_title || 'غير محدد'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="font-bold">${totalAmount.toFixed(2)} ج.م</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <div class="flex flex-col">
                        <span class="text-green-600 font-bold">${profitAmount.toFixed(2)} ج.م</span>
                        <span class="text-xs text-gray-500">(10%)</span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${this.formatDate(deal.created_at)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-2">
                        <button onclick="event.stopPropagation(); adminDeals.viewDeal(${deal.id})" 
                                class="text-blue-600 hover:text-blue-900 px-2 py-1 rounded hover:bg-blue-50">
                            <i class="fas fa-eye"></i> عرض
                        </button>
                        <button onclick="event.stopPropagation(); adminDeals.viewConversation(${deal.conversation_id})" 
                                class="text-green-600 hover:text-green-900 px-2 py-1 rounded hover:bg-green-50">
                            <i class="fas fa-comments"></i> المحادثة
                        </button>
                    </div>
                </td>
            </tr>
        `;
        }).join('');
    }

    updateStats(deals) {
        // حساب إجمالي المبالغ المعلقة
        const totalEscrow = deals.reduce((sum, deal) => sum + (parseFloat(deal.amount) || 0), 0);
        
        // حساب إجمالي الربح المتوقع (10% من كل صفقة)
        const totalProfit = deals.reduce((sum, deal) => {
            const amount = parseFloat(deal.amount) || 0;
            const profit = amount * 0.10; // دائماً 10%
            return sum + profit;
        }, 0);
        
        // تحديث العناصر في الصفحة
        document.getElementById('pendingCount').textContent = deals.length;
        document.getElementById('totalEscrow').textContent = totalEscrow.toFixed(2) + ' ج.م';
        document.getElementById('totalProfit').textContent = totalProfit.toFixed(2) + ' ج.م';
        
        // حساب الصفقات المكتملة اليوم (إذا كانت البيانات متاحة)
        const today = new Date().toDateString();
        const completedToday = deals.filter(deal => {
            const dealDate = new Date(deal.created_at).toDateString();
            return dealDate === today && deal.status === 'COMPLETED';
        }).length;
        document.getElementById('completedToday').textContent = completedToday;
    }

    async viewDeal(dealId) {
        try {
            this.showLoading();
            const response = await fetch(`api/admin_deals.php?action=details&deal_id=${dealId}`);
            const data = await response.json();
            
            if (data.success) {
                this.currentDealId = dealId;
                this.showDealDetails(data.deal);
            } else {
                this.showError('فشل في تحميل تفاصيل الصفقة: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading deal details:', error);
            this.showError('حدث خطأ في تحميل تفاصيل الصفقة');
        } finally {
            this.hideLoading();
        }
    }

    showDealDetails(deal) {
        const detailsContainer = document.getElementById('dealDetails');
        
        // حساب مبالغ الربح
        const totalAmount = parseFloat(deal.amount) || 0;
        const profitAmount = totalAmount * 0.10; // دائماً 10%
        const sellerAmount = totalAmount - profitAmount;
        
        detailsContainer.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">رقم الصفقة</label>
                        <p class="text-sm text-gray-900 font-mono">#${deal.id}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">المشتري</label>
                        <p class="text-sm text-gray-900">${deal.buyer_name} (${deal.buyer_email || deal.buyer_phone || 'غير محدد'})</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">البائع</label>
                        <p class="text-sm text-gray-900">${deal.seller_name} (${deal.seller_email || deal.seller_phone || 'غير محدد'})</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">المبلغ الإجمالي</label>
                        <p class="text-sm text-gray-900 font-semibold">${totalAmount.toFixed(2)} ج.م</p>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">الحساب</label>
                        <p class="text-sm text-gray-900">${deal.game_name || deal.account_title || 'غير محدد'}</p>
                        <p class="text-xs text-gray-500">${deal.account_description || 'لا يوجد وصف'}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">تاريخ بدء الصفقة</label>
                        <p class="text-sm text-gray-900">${this.formatDate(deal.created_at)}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">تاريخ تأكيد الاستلام</label>
                        <p class="text-sm text-gray-900">${this.formatDate(deal.updated_at)}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">الحالة</label>
                        <span class="status-badge status-delivered">
                            <i class="fas fa-clock ml-1"></i>
                            في انتظار المراجعة
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- تفاصيل مالية -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="text-lg font-medium text-gray-900 mb-4">
                    <i class="fas fa-calculator ml-2"></i>التفاصيل المالية
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center p-3 bg-white rounded border">
                        <div class="text-sm text-gray-600">المبلغ الإجمالي</div>
                        <div class="text-lg font-bold text-gray-900">${totalAmount.toFixed(2)} ج.م</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded border border-green-200">
                        <div class="text-sm text-green-600">مبلغ الربح (10%)</div>
                        <div class="text-lg font-bold text-green-600">${profitAmount.toFixed(2)} ج.م</div>
                    </div>
                    <div class="text-center p-3 bg-blue-50 rounded border border-blue-200">
                        <div class="text-sm text-blue-600">مبلغ البائع</div>
                        <div class="text-lg font-bold text-blue-600">${sellerAmount.toFixed(2)} ج.م</div>
                    </div>
                </div>
            </div>
            
            ${deal.messages && deal.messages.length > 0 ? `
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">آخر الرسائل</label>
                    <div class="bg-gray-50 rounded-lg p-4 max-h-60 overflow-y-auto">
                        ${deal.messages.map(msg => `
                            <div class="mb-3 last:mb-0">
                                <div class="flex justify-between items-start">
                                    <span class="font-medium text-sm">${msg.sender_name}</span>
                                    <span class="text-xs text-gray-500">${this.formatDate(msg.created_at)}</span>
                                </div>
                                <p class="text-sm text-gray-700 mt-1">${msg.message}</p>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        `;
        
        document.getElementById('dealModal').classList.remove('hidden');
    }

    async viewConversation(conversationId) {
        // Redirect to messages page with conversation ID
        window.open(`messages.php?conversation_id=${conversationId}`, '_blank');
    }

    async approveDeal() {
        if (!this.currentDealId) return;
        
        if (!confirm('هل أنت متأكد من اعتماد هذه الصفقة؟ سيتم تحويل المبلغ إلى البائع.')) {
            return;
        }
        
        try {
            this.showLoading();
            const response = await fetch('api/admin_deals.php?action=approve', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    deal_id: this.currentDealId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('تم اعتماد الصفقة بنجاح');
                this.closeModal();
                this.loadDeals();
                this.loadStats();
            } else {
                this.showError('فشل في اعتماد الصفقة: ' + data.message);
            }
        } catch (error) {
            console.error('Error approving deal:', error);
            this.showError('حدث خطأ في اعتماد الصفقة');
        } finally {
            this.hideLoading();
        }
    }

    showRejectModal() {
        document.getElementById('rejectModal').classList.remove('hidden');
        document.getElementById('rejectReason').value = '';
        document.getElementById('rejectReason').focus();
    }

    hideRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }

    async confirmReject() {
        const reason = document.getElementById('rejectReason').value.trim();
        
        if (!reason) {
            alert('يرجى كتابة سبب رفض الصفقة');
            return;
        }
        
        if (!this.currentDealId) return;
        
        try {
            this.showLoading();
            const response = await fetch('api/admin_deals.php?action=reject', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    deal_id: this.currentDealId,
                    reason: reason
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('تم رفض الصفقة وإرجاع المبلغ للمشتري');
                this.hideRejectModal();
                this.closeModal();
                this.loadDeals();
                this.loadStats();
            } else {
                this.showError('فشل في رفض الصفقة: ' + data.message);
            }
        } catch (error) {
            console.error('Error rejecting deal:', error);
            this.showError('حدث خطأ في رفض الصفقة');
        } finally {
            this.hideLoading();
        }
    }

    closeModal() {
        document.getElementById('dealModal').classList.add('hidden');
        this.currentDealId = null;
    }

    showLoading() {
        document.getElementById('loadingSpinner').classList.remove('hidden');
    }

    hideLoading() {
        document.getElementById('loadingSpinner').classList.add('hidden');
    }

    showSuccess(message) {
        // Create and show success notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 left-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-check-circle ml-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    showError(message) {
        // Create and show error notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 left-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('ar-EG', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

// Initialize the admin deals manager
let adminDeals;

document.addEventListener('DOMContentLoaded', function() {
    adminDeals = new AdminDealsManager();
});