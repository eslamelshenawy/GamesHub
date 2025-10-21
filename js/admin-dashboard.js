// Admin Dashboard JavaScript - Real Data Only

class AdminDashboard {
    constructor() {
        this.currentSection = 'dashboard';
        this.currentDealId = null;
        this.currentTopupId = null;
        this.sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        this.init();
    }

    init() {
        this.checkAdminAuth();
        this.bindEvents();
        this.setupConversationModalEvents();
        this.loadDashboardData();
        this.updateTime();
        
        // Refresh data every 30 seconds
        setInterval(() => {
            this.loadDashboardData();
        }, 30000);
        
        // Update time every second
        setInterval(() => {
            this.updateTime();
        }, 1000);
        
        // Show dashboard section by default
        this.showSection('dashboard');
    }

    checkAdminAuth() {
        // Skip auth check for testing - use JSON file for development
        // In production, use check_admin.php
        return true;
        
        // Production auth check (commented out for testing):
        // fetch('/api/api/check_admin.php')
        //     .then(response => {
        //         if (!response.ok) {
        //             throw new Error('Network response was not ok');
        //         }
        //         return response.json();
        //     })
        //     .then(data => {
        //         if (!data.success || !data.is_admin) {
        //             console.log('Admin auth failed:', data);
        //             // Only redirect if explicitly not admin, not on network errors
        //             if (data.success === false && data.is_admin === false) {
        //                 window.location.href = 'login.html';
        //             }
        //         }
        //     })
        //     .catch(error => {
        //         console.error('Auth check failed:', error);
        //         // Don't redirect on network errors, just log them
        //         // This prevents redirecting when the API is not available during development
        //     });
    }

    bindEvents() {
        // Sidebar navigation
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const href = item.getAttribute('href');
                const onclickAttr = item.getAttribute('onclick');

                // Allow navigation if href exists and is not '#'
                if (href && href !== '#' && (!onclickAttr || !onclickAttr.includes('showSection'))) {
                    // Let the link navigate normally
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                // Skip if onclick doesn't contain showSection (e.g., logout button)
                if (!onclickAttr || !onclickAttr.includes('showSection')) {
                    return;
                }

                // Remove active class from all items
                document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('bg-neon-blue/20'));

                // Add active class to clicked item
                item.classList.add('bg-neon-blue/20');

                const section = onclickAttr.match(/showSection\('(.+)'\)/)[1];
                this.showSection(section);

                // Close mobile sidebar after navigation
                if (window.innerWidth < 1024) {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar) {
                        sidebar.classList.remove('sidebar-open');
                    }
                }
            });
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                sidebar.classList.toggle('sidebar-open');
                
                // Prevent body scroll when sidebar is open
                if (sidebar.classList.contains('sidebar-open')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            });
        }

        // Desktop sidebar toggle
        const desktopToggle = document.getElementById('desktopSidebarToggle');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        if (desktopToggle) {
            desktopToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleSidebar();
            });
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleSidebar();
            });
        }

        // Close sidebar when clicking outside (mobile)
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 1024) {
                const sidebar = document.getElementById('sidebar');
                const mobileMenuBtn = document.getElementById('mobileMenuBtn');
                
                if (sidebar && sidebar.classList.contains('sidebar-open') && 
                    !sidebar.contains(e.target) && 
                    !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('sidebar-open');
                    document.body.style.overflow = '';
                }
            }
        });

        // Handle escape key to close mobile sidebar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && window.innerWidth < 1024) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar && sidebar.classList.contains('sidebar-open')) {
                    sidebar.classList.remove('sidebar-open');
                    document.body.style.overflow = '';
                }
            }
        });

        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.adjustTableResponsiveness();
                
                // Close mobile sidebar on desktop and restore body scroll
                if (window.innerWidth >= 1024) {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar) {
                        sidebar.classList.remove('sidebar-open');
                        document.body.style.overflow = '';
                    }
                }
            }, 250);
        });

        // Top-up/Withdrawal tabs
        const topupTab = document.getElementById('topupTab');
        const withdrawTab = document.getElementById('withdrawTab');
        if (topupTab && withdrawTab) {
            topupTab.addEventListener('click', () => this.switchTopupTab('topup'));
            withdrawTab.addEventListener('click', () => this.switchTopupTab('withdraw'));
        }

        // Reports filter and refresh
        const reportStatusFilter = document.getElementById('reportStatusFilter');
        const refreshReports = document.getElementById('refreshReports');
        
        if (reportStatusFilter) {
            reportStatusFilter.addEventListener('change', () => {
                this.filterReports(reportStatusFilter.value);
            });
        }
        
        if (refreshReports) {
            refreshReports.addEventListener('click', () => {
                this.loadReports();
                this.showNotification('تم تحديث البلاغات', 'success');
            });
        }

        // Modal events
        this.bindModalEvents()
    }

    bindModalEvents() {
        // Remove existing event listeners to prevent duplicates
        this.removeModalEventListeners();
        
        // Receipt modal
        const closeReceiptModal = document.getElementById('closeReceiptModal');
        if (closeReceiptModal) {
            closeReceiptModal.addEventListener('click', () => this.closeReceiptModal());
        }

        // Deal modal
        const closeDealModal = document.getElementById('closeDealModal');
        const cancelDealModal = document.getElementById('cancelDealModal');
        const approveDeal = document.getElementById('approveDeal');
        const rejectDeal = document.getElementById('rejectDeal');
        const viewConversation = document.getElementById('viewConversation');
        
        if (closeDealModal) closeDealModal.addEventListener('click', () => this.closeDealModal());
        if (cancelDealModal) cancelDealModal.addEventListener('click', () => this.closeDealModal());
        if (approveDeal) approveDeal.addEventListener('click', () => this.approveDeal());
        if (rejectDeal) rejectDeal.addEventListener('click', () => this.showRejectModal());
        if (viewConversation) viewConversation.addEventListener('click', () => {
            if (this.currentDealId) {
                this.openConversationModal(this.currentDealId);
            }
        });

        // Reject modal
        const closeRejectModal = document.getElementById('closeRejectModal');
        const cancelReject = document.getElementById('cancelReject');
        const confirmReject = document.getElementById('confirmReject');
        
        if (closeRejectModal) closeRejectModal.addEventListener('click', () => this.closeRejectModal());
        if (cancelReject) cancelReject.addEventListener('click', () => this.closeRejectModal());
        if (confirmReject) confirmReject.addEventListener('click', () => this.confirmReject());
        
        // Report Details modal
        const closeReportModal = document.getElementById('closeReportModal');
        const resolveReport = document.getElementById('resolveReport');
        const dismissReport = document.getElementById('dismissReport');
        const viewReportedConversation = document.getElementById('viewReportedConversation');
        const chatWithReporter = document.getElementById('chatWithReporter');
        const chatWithReported = document.getElementById('chatWithReported');
        
        if (closeReportModal) closeReportModal.addEventListener('click', () => this.closeReportModal());
        if (resolveReport) resolveReport.addEventListener('click', () => {
            if (this.currentReportId) {
                this.resolveReport(this.currentReportId);
            }
        });
        if (dismissReport) dismissReport.addEventListener('click', () => {
            if (this.currentReportId) {
                this.rejectReport(this.currentReportId);
            }
        });
        
        // Chat buttons event listeners
        if (viewReportedConversation) viewReportedConversation.addEventListener('click', () => {
            console.log('🔵 View Reported Conversation button clicked!');
            console.log('📌 Current Report ID:', this.currentReportId);
            console.log('📌 Current Report:', this.currentReport);
            if (this.currentReportId) {
                this.viewReportedConversation(this.currentReportId);
            } else {
                console.error('❌ No currentReportId set!');
                this.showNotification('خطأ: لم يتم تحديد البلاغ', 'error');
            }
        });
        
        if (chatWithReporter) chatWithReporter.addEventListener('click', () => {
            console.log('Current report:', JSON.stringify(this.currentReport, null, 2));
            const reporterId = this.currentReport?.reporter?.id || this.currentReport?.reporter_id;
            if (this.currentReport && reporterId) {
                this.openAdminChat(reporterId, 'reporter');
            } else {
                this.showNotification('معرف المبلغ غير متوفر', 'error');
                console.error('Reporter ID not found:', JSON.stringify(this.currentReport, null, 2));
            }
        });
        
        if (chatWithReported) chatWithReported.addEventListener('click', () => {
            console.log('Current report:', JSON.stringify(this.currentReport, null, 2));
            const reportedUserId = this.currentReport?.reported_user?.id || this.currentReport?.reported_user_id;
            if (this.currentReport && reportedUserId) {
                this.openAdminChat(reportedUserId, 'reported');
            } else {
                this.showNotification('معرف المبلغ عنه غير متوفر', 'error');
                console.error('Reported user ID not found:', JSON.stringify(this.currentReport, null, 2));
            }
        });
        
        // Ban buttons event listeners
        const banReporter = document.getElementById('banReporter');
        const banReported = document.getElementById('banReported');
        
        if (banReporter) banReporter.addEventListener('click', () => {
            const reporterId = this.currentReport?.reporter?.id || this.currentReport?.reporter_id;
            if (this.currentReport && reporterId) {
                this.banUserFromReport(reporterId, 'المبلغ');
            } else {
                this.showNotification('معرف المبلغ غير متوفر', 'error');
            }
        });
        
        if (banReported) banReported.addEventListener('click', () => {
            const reportedUserId = this.currentReport?.reported_user?.id || this.currentReport?.reported_user_id;
            if (this.currentReport && reportedUserId) {
                this.banUserFromReport(reportedUserId, 'المبلغ عنه');
            } else {
                this.showNotification('معرف المبلغ عنه غير متوفر', 'error');
            }
        });
    }

    removeModalEventListeners() {
        // Remove ban button event listeners
        const banReporter = document.getElementById('banReporter');
        const banReported = document.getElementById('banReported');
        
        if (banReporter) {
            banReporter.replaceWith(banReporter.cloneNode(true));
        }
        if (banReported) {
            banReported.replaceWith(banReported.cloneNode(true));
        }
    }

    updateTime() {
        const now = new Date();
        const timeString = now.toLocaleString('ar-EG', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }

    async loadDashboardData() {
        try {
            // Load main statistics from admin_stats.php
            const statsResponse = await fetch('/api/api/admin_stats.php');
            if (statsResponse.ok) {
                const statsData = await statsResponse.json();
                if (statsData.success) {
                    this.updateStatistics(statsData);
                }
            }
            
            // Load profits statistics from profits_stats.php
            const profitsResponse = await fetch('/api/api/profits_stats.php');
            if (profitsResponse.ok) {
                const profitsData = await profitsResponse.json();
                if (profitsData.success) {
                    this.updateProfitsStatistics(profitsData.data);
                }
            }
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            // Fallback to show some basic info if API fails
            this.showNotification('خطأ في تحميل البيانات', 'error');
        }
    }

    updateStatistics(data) {
        if (!data || !data.users) return;
        
        // Update main stats cards
        this.updateElement('totalUsers', data.users.total_users || 0);
        this.updateElement('onlineUsers', data.users.online_users || 0);
        this.updateElement('totalDeals', data.deals?.total_deals || 0);
        this.updateElement('completedDeals', data.deals?.completed_deals || 0);
        this.updateElement('totalAccounts', data.accounts?.total_accounts || 0);
        this.updateElement('accountsToday', data.accounts?.accounts_today || 0);
        this.updateElement('totalMessages', data.messages?.total_messages || 0);
        this.updateElement('messagesToday', data.messages?.messages_today || 0);
        
        // Update financial stats
        this.updateElement('totalBalance', this.formatCurrency(data.wallets?.balance_totals?.total_balance || 0));
        this.updateElement('pendingBalance', data.wallets?.balance_totals?.total_pending || 0);
        this.updateElement('totalPayments', data.wallets?.payment_totals?.total_payments || 0);
        this.updateElement('totalPaymentAmount', this.formatCurrency(data.wallets?.payment_totals?.total_payment_amount || 0));
        this.updateElement('completionRate', (data.performance?.completion_rate || 0).toFixed(1) + '%');
        this.updateElement('avgCompletionHours', data.performance?.avg_completion_hours || 0);
        
        // Update top performers
        this.updateTopPerformers(data.top_sellers || [], data.top_buyers || []);
        
        // Update game statistics
        this.updateGameStats(data.accounts?.accounts_by_game || []);
    }
    
    updateProfitsStatistics(data) {
        if (!data) return;
        // Update profits cards
        this.updateElement('totalProfits', this.formatCurrency(data.total_stats?.total_profits || 0));
        this.updateElement('profitsToday', this.formatCurrency(data.today_stats?.profits_today || 0));
        // Update profits analytics
        this.updateElement('totalProfitsAmount', this.formatCurrency(data.total_stats?.total_profits || 0));
        // حساب المتوسط يدوياً لضمان الدقة
        const totalProfit = parseFloat(data.total_stats?.total_profits || 0);
        const totalDeals = parseInt(data.total_stats?.total_deals || 0);
        const avgProfitPerDeal = totalDeals > 0 ? (totalProfit / totalDeals) : 0;
        this.updateElement('avgProfitPerDeal', this.formatCurrency(avgProfitPerDeal));
        
        // Update new detailed profits statistics cards
        this.updateElement('totalValidProfits', this.formatCurrency(data.total_stats?.total_profits || 0));
        this.updateElement('completedValidDeals', data.total_stats?.total_deals || 0);
        this.updateElement('avgValidProfitPerDeal', this.formatCurrency(avgProfitPerDeal));
        
        // Update monthly profits
        this.updateElement('thisMonthProfit', this.formatCurrency(data.this_month_stats?.profits_this_month || 0));
        this.updateElement('lastMonthProfit', this.formatCurrency(data.last_month_stats?.profits_last_month || 0));
        this.updateElement('profitGrowth', (data.profit_growth || 0) + '%');
        // Update charts
        this.updateProfitsChart(data);
        this.updateMonthlyProfitsChart(data.monthly_stats || []);
    }
    
    updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }
    
    formatCurrency(amount) {
        return parseFloat(amount || 0).toLocaleString('ar-EG') + ' ج.م';
    }
    
    updateProfitsChart(data) {
        const ctx = document.getElementById('profitsChart');
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (this.profitsChart) {
            this.profitsChart.destroy();
        }
        
        const gameProfits = data.game_profits || [];
        const labels = gameProfits.map(game => game.game_name || 'غير محدد');
        const profits = gameProfits.map(game => parseFloat(game.total_profits) || 0);
        
        this.profitsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: profits,
                    backgroundColor: [
                        '#9b59ff',
                        '#00d4ff',
                        '#39ff14',
                        '#ff00ff',
                        '#ffff00',
                        '#ff8c00',
                        '#00ffff',
                        '#ff073a',
                        '#00ffa3',
                        '#ccff00'
                    ],
                    borderWidth: 2,
                    borderColor: '#1a1f2e'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            font: {
                                family: 'Tajawal'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed.toFixed(2) + ' ج.م';
                            }
                        }
                    }
                }
            }
        });
    }
    
    updateMonthlyProfitsChart(monthlyData) {
        const ctx = document.getElementById('monthlyProfitsChart');
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (this.monthlyProfitsChart) {
            this.monthlyProfitsChart.destroy();
        }
        
        // Sort data by month
        const sortedData = monthlyData.sort((a, b) => a.month.localeCompare(b.month));
        const labels = sortedData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('ar-EG', { year: 'numeric', month: 'short' });
        });
        const profits = sortedData.map(item => parseFloat(item.monthly_profits) || 0);
        
        this.monthlyProfitsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'الأرباح الشهرية',
                    data: profits,
                    backgroundColor: 'rgba(57, 255, 20, 0.2)',
                    borderColor: '#39ff14',
                    borderWidth: 2,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'الأرباح: ' + context.parsed.y.toFixed(2) + ' ج.م';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                family: 'Tajawal'
                            },
                            callback: function(value) {
                                return value.toFixed(0) + ' ج.م';
                            }
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                family: 'Tajawal'
                            }
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.1)'
                        }
                    }
                }
            }
        });
    }
    
    updateTopPerformers(topSellers, topBuyers) {
        // Update top sellers
        const sellersContainer = document.getElementById('topSellers');
        if (sellersContainer) {
            sellersContainer.innerHTML = topSellers.length > 0 ? 
                topSellers.map(seller => `
                    <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                        <div>
                            <p class="font-medium text-white">${seller.name}</p>
                            <p class="text-sm text-text-muted">${seller.phone}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-neon-green font-bold">${this.formatCurrency(seller.total_sales)}</p>
                            <p class="text-xs text-text-muted">${seller.deal_count} صفقة</p>
                        </div>
                    </div>
                `).join('') : 
                '<p class="text-text-muted text-center py-4">لا توجد بيانات</p>';
        }
        
        // Update top buyers
        const buyersContainer = document.getElementById('topBuyers');
        if (buyersContainer) {
            buyersContainer.innerHTML = topBuyers.length > 0 ? 
                topBuyers.map(buyer => `
                    <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg">
                        <div>
                            <p class="font-medium text-white">${buyer.name}</p>
                            <p class="text-sm text-text-muted">${buyer.phone}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-neon-blue font-bold">${this.formatCurrency(buyer.total_purchases)}</p>
                            <p class="text-xs text-text-muted">${buyer.deal_count} صفقة</p>
                        </div>
                    </div>
                `).join('') : 
                '<p class="text-text-muted text-center py-4">لا توجد بيانات</p>';
        }
    }
    
    updateGameStats(gameStats) {
        const gameStatsContainer = document.getElementById('gameStats');
        if (gameStatsContainer) {
            gameStatsContainer.innerHTML = gameStats.length > 0 ? 
                gameStats.map(game => `
                    <div class="bg-gray-800/50 p-4 rounded-lg">
                        <h4 class="font-medium text-white mb-2">${game.game_name}</h4>
                        <p class="text-2xl font-bold text-neon-purple">${game.count}</p>
                        <p class="text-xs text-text-muted">حساب</p>
                    </div>
                `).join('') : 
                '<p class="text-text-muted text-center py-4 col-span-full">لا توجد بيانات</p>';
        }
    }

    showSection(section) {
        // Hide all sections
        document.querySelectorAll('[id$="Section"]').forEach(sec => {
            sec.style.display = 'none';
        });

        // Update sidebar active state
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.classList.remove('active', 'bg-neon-blue/20');
        });
        
        const activeItem = document.querySelector(`[onclick="showSection('${section}')"]`);
        if (activeItem) {
            activeItem.classList.add('active', 'bg-neon-blue/20');
        }

        this.currentSection = section;

        // Show selected section and load its data
        switch (section) {
            case 'dashboard':
                const dashboardSection = document.getElementById('dashboardSection');
                if (dashboardSection) {
                    dashboardSection.style.display = 'block';
                }
                this.loadDashboardData();
                break;
            case 'users':
                this.showUsersSection();
                break;
            case 'deals':
                this.showDealsSection();
                break;
            case 'topups':
                this.showTopupsSection();
                break;
            case 'reports':
                this.showReportsSection();
                break;
        }
    }

    async showUsersSection() {
        const section = document.getElementById('usersSection');
        if (section) {
            section.style.display = 'block';
            await this.loadUsers();
        }
    }

    async showDealsSection() {
        const section = document.getElementById('dealsSection');
        if (section) {
            section.style.display = 'block';
            await this.loadDeals();
        }
    }

    async showTopupsSection() {
        const section = document.getElementById('topupsSection');
        if (section) {
            section.style.display = 'block';
            await this.loadTopups();
        }
    }

    async loadUsers() {
        try {
            const response = await fetch('/api/api/get_users.php');
            const data = await response.json();
            
            if (data.success) {
                this.renderUsers(data.users);
            } else {
                this.showNotification(data.message || 'حدث خطأ أثناء جلب المستخدمين', 'error');
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showNotification('حدث خطأ أثناء جلب المستخدمين', 'error');
        }
    }

    renderUsers(users) {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        tbody.innerHTML = users.map(user => `
            <tr class="border-b border-gray-700">
                <td class="px-3 lg:px-6 py-4" data-label="الاسم">${user.name}</td>
                <td class="px-3 lg:px-6 py-4 hidden sm:table-cell" data-label="الهاتف">${user.phone}</td>
                <td class="px-3 lg:px-6 py-4 hidden md:table-cell" data-label="تاريخ التسجيل">${user.created_at ? new Date(user.created_at).toLocaleDateString('ar-EG') : 'غير محدد'}</td>
                <td class="px-3 lg:px-6 py-4" data-label="الحالة">
                    <span class="px-2 py-1 rounded-full text-xs ${
                        user.role === 'user' ? 'bg-neon-green/20 text-neon-green' : user.role === 'banned' ? 'bg-red-500/20 text-red-400' : 'bg-gray-500/20 text-gray-400'
                    }">
                        ${user.role === 'user' ? 'نشط' : user.role === 'banned' ? 'محظور' : user.role === 'admin' ? 'مدير' : 'غير نشط'}
                    </span>
                </td>
                <td class="px-3 lg:px-6 py-4" data-label="الرصيد">${user.wallet_balance || 0} ج.م</td>
                <td class="px-3 lg:px-6 py-4" data-label="الإجراءات">
                    <div class="flex flex-col sm:flex-row gap-2">
                        ${user.role === 'banned' ? 
                            `<button onclick="window.adminDashboard.unbanUser(${user.id})" class="bg-neon-green text-white px-3 py-1 rounded text-xs hover:bg-neon-green/80 transition-colors">
                                <i class="fas fa-unlock ml-1"></i>
                                إلغاء الحظر
                            </button>` :
                            `<button onclick="window.adminDashboard.banUser(${user.id})" class="bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700 transition-colors">
                                <i class="fas fa-ban ml-1"></i>
                                حظر الحساب
                            </button>`
                        }
                        <button onclick="window.adminDashboard.viewUserDetails(${user.id})" class="bg-neon-blue text-white px-3 py-1 rounded text-xs hover:bg-neon-blue/80 transition-colors">
                            <i class="fas fa-eye ml-1"></i>
                            عرض التفاصيل
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    async loadDeals() {
        try {
            const response = await fetch('/api/api/admin_deals.php?action=get_pending_deals');
            const data = await response.json();
            
            if (data.success) {
                this.renderDeals(data.deals);
            }
        } catch (error) {
            console.error('Error loading deals:', error);
        }
    }

    renderDeals(deals) {
        const tbody = document.getElementById('dealsTableBody');
        if (!tbody) return;

        tbody.innerHTML = deals.map(deal => {
            // حساب مبلغ الربح (10% من المبلغ الإجمالي)
            const totalAmount = parseFloat(deal.amount) || 0;
            const profitAmount = totalAmount * 0.10; // دائماً 10%
            const sellerAmount = totalAmount - profitAmount;
            
            return `
            <tr class="border-b border-gray-700 hover:bg-gray-800/50 transition-colors">
                <td class="px-6 py-4" data-label="رقم الصفقة">
                    <span class="font-mono text-neon-blue">#${deal.id}</span>
                </td>
                <td class="px-6 py-4" data-label="المشتري">${deal.buyer_name}</td>
                <td class="px-6 py-4" data-label="البائع">${deal.seller_name}</td>
                <td class="px-6 py-4" data-label="الحساب">${deal.game_name}</td>
                <td class="px-6 py-4" data-label="المبلغ الإجمالي">
                    <span class="font-bold text-white">${totalAmount.toFixed(2)} ج.م</span>
                </td>
                <td class="px-6 py-4" data-label="مبلغ الربح">
                    <div class="flex flex-col space-y-1">
                        <span class="text-green-400 font-bold">${profitAmount.toFixed(2)} ج.م</span>
                        <span class="text-xs text-gray-400">(10%)</span>
                    </div>
                </td>
                <td class="px-6 py-4" data-label="التاريخ">${new Date(deal.created_at).toLocaleDateString('ar-EG')}</td>
                <td class="px-6 py-4" data-label="الإجراءات">
                    <div class="flex space-x-2">
                        <button onclick="adminDashboard.viewDeal(${deal.id})" class="bg-neon-blue text-white px-3 py-1 rounded text-xs hover:bg-neon-blue/80 transition-colors">
                            <i class="fas fa-eye ml-1"></i>عرض
                        </button>
                        <button onclick="adminDashboard.releaseFundsToSeller(${deal.id})" class="bg-yellow-500 text-white px-3 py-1 rounded text-xs hover:bg-yellow-600 transition-colors" title="تحرير الأموال للبائع بدون تأكيد المشتري">
                            <i class="fas fa-money-bill-wave ml-1"></i>تحرير الأموال
                        </button>
                    </div>
                </td>
            </tr>
        `;
        }).join('');
    }

    async loadTopups() {
        try {
            const [topupsResponse, withdrawalsResponse] = await Promise.all([
                fetch('/api/api/get_topup_requests.php'),
                fetch('/api/api/get_withdraw_requests.php')
            ]);
            
            const topupsData = await topupsResponse.json();
            const withdrawalsData = await withdrawalsResponse.json();
            
            if (topupsData.success) {
                this.renderTopups(topupsData.requests);
            }
            
            if (withdrawalsData.success) {
                this.renderWithdrawals(withdrawalsData.requests);
            }
        } catch (error) {
            console.error('Error loading topups:', error);
        }
    }

    renderTopups(topups) {
        const tbody = document.getElementById('topupTableBody');
        if (!tbody) return;

        tbody.innerHTML = topups.map(topup => `
            <tr class="border-b border-gray-700">
                <td class="px-6 py-4" data-label="المستخدم">${topup.username || 'غير محدد'}</td>
                <td class="px-6 py-4" data-label="الهاتف">${topup.phone || 'غير محدد'}</td>
                <td class="px-6 py-4" data-label="المبلغ">${topup.amount} ج.م</td>
                <td class="px-6 py-4" data-label="الطريقة">${topup.method || 'غير محدد'}</td>
                <td class="px-6 py-4" data-label="الحالة">
                    <span class="px-2 py-1 rounded-full text-xs ${
                        topup.status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                        topup.status === 'approved' ? 'bg-neon-green/20 text-neon-green' :
                        'bg-red-500/20 text-red-400'
                    }">
                        ${topup.status === 'pending' ? 'معلق' : topup.status === 'approved' ? 'مقبول' : 'مرفوض'}
                    </span>
                </td>
                <td class="px-6 py-4" data-label="التاريخ">${new Date(topup.created_at).toLocaleDateString('ar-EG')}</td>
                <td class="px-6 py-4" data-label="الإجراءات">
                    ${topup.status === 'pending' ? `
                        <div class="flex space-x-1 space-x-reverse">
                            ${topup.receipt && topup.receipt !== '' ? `
                                <button onclick="adminDashboard.viewReceipt('${topup.receipt}')" class="bg-neon-purple text-white px-2 py-1 rounded text-xs">
                                    عرض الإيصال
                                </button>
                            ` : ''}
                            <button onclick="adminDashboard.approveTopup(${topup.id})" class="bg-neon-green text-white px-2 py-1 rounded text-xs">
                                قبول
                            </button>
                            <button onclick="adminDashboard.rejectTopup(${topup.id})" class="bg-red-600 text-white px-2 py-1 rounded text-xs">
                                رفض
                            </button>
                        </div>
                    ` : '-'}
                </td>
            </tr>
        `).join('');
    }

    renderWithdrawals(withdrawals) {
        const tbody = document.getElementById('withdrawTableBody');
        if (!tbody) return;

        tbody.innerHTML = withdrawals.map(withdrawal => `
            <tr class="border-b border-gray-700">
                <td class="px-6 py-4" data-label="المستخدم">${withdrawal.name || 'غير محدد'}</td>
                <td class="px-6 py-4" data-label="الهاتف">${withdrawal.phone || 'غير محدد'}</td>
                <td class="px-6 py-4" data-label="المبلغ">${withdrawal.amount} ج.م</td>
                <td class="px-6 py-4" data-label="الطريقة">${withdrawal.method || 'غير محدد'}</td>
                <td class="px-6 py-4" data-label="الحالة">
                    <span class="px-2 py-1 rounded-full text-xs ${
                        withdrawal.status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                        withdrawal.status === 'approved' ? 'bg-neon-green/20 text-neon-green' :
                        'bg-red-500/20 text-red-400'
                    }">
                        ${withdrawal.status === 'pending' ? 'معلق' : withdrawal.status === 'approved' ? 'مقبول' : 'مرفوض'}
                    </span>
                </td>
                <td class="px-6 py-4" data-label="التاريخ">${new Date(withdrawal.created_at).toLocaleDateString('ar-EG')}</td>
                <td class="px-6 py-4" data-label="الإجراءات">
                    ${withdrawal.status === 'pending' ? `
                        <div class="flex space-x-1 space-x-reverse">
                            <button onclick="adminDashboard.approveWithdraw(${withdrawal.id})" class="bg-neon-green text-white px-2 py-1 rounded text-xs">
                                قبول
                            </button>
                            <button onclick="adminDashboard.rejectWithdraw(${withdrawal.id})" class="bg-red-600 text-white px-2 py-1 rounded text-xs">
                                رفض
                            </button>
                        </div>
                    ` : '-'}
                </td>
            </tr>
        `).join('');
    }

    async loadWithdrawals() {
        await this.loadTopups();
    }

    switchTopupTab(tab) {
        const topupTab = document.getElementById('topupTab');
        const withdrawTab = document.getElementById('withdrawTab');
        const topupRequests = document.getElementById('topupRequests');
        const withdrawRequests = document.getElementById('withdrawRequests');

        if (tab === 'topup') {
            topupTab.classList.add('bg-neon-blue');
            topupTab.classList.remove('bg-gray-600');
            withdrawTab.classList.add('bg-gray-600');
            withdrawTab.classList.remove('bg-neon-blue');
            topupRequests.style.display = 'block';
            withdrawRequests.style.display = 'none';
        } else {
            withdrawTab.classList.add('bg-neon-blue');
            withdrawTab.classList.remove('bg-gray-600');
            topupTab.classList.add('bg-gray-600');
            topupTab.classList.remove('bg-neon-blue');
            withdrawRequests.style.display = 'block';
            topupRequests.style.display = 'none';
        }
    }

    // Modal functions
    viewReceipt(imagePath) {
        const modal = document.getElementById('receiptModal');
        const image = document.getElementById('receiptImage');
        if (modal && image) {
            image.src = imagePath;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    closeReceiptModal() {
        const modal = document.getElementById('receiptModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    async viewDeal(dealId) {
        try {
            const response = await fetch(`api/admin_deals.php?action=details&id=${dealId}`);
            const data = await response.json();
            
            if (data.success) {
                this.currentDealId = dealId;
                this.showDealModal(data.deal);
            }
        } catch (error) {
            console.error('Error loading deal details:', error);
        }
    }

    showDealModal(deal) {
        const modal = document.getElementById('dealModal');
        const details = document.getElementById('dealDetails');
        
        if (modal && details) {
            details.innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div><strong>رقم الصفقة:</strong> #${deal.id}</div>
                    <div><strong>المبلغ:</strong> ${deal.amount} ج.م</div>
                    <div><strong>المشتري:</strong> ${deal.buyer_name}</div>
                    <div><strong>البائع:</strong> ${deal.seller_name}</div>
                    <div><strong>نوع الحساب:</strong> ${deal.account_type}</div>
                    <div><strong>التاريخ:</strong> ${new Date(deal.created_at).toLocaleDateString('ar-EG')}</div>
                </div>
                <div class="mt-4">
                    <strong>الوصف:</strong>
                    <p class="mt-2 p-3 bg-gray-700 rounded">${deal.description || 'لا يوجد وصف'}</p>
                </div>
            `;
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    closeDealModal() {
        const modal = document.getElementById('dealModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            this.currentDealId = null;
        }
    }

    showRejectModal() {
        const modal = document.getElementById('rejectModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        const textarea = document.getElementById('rejectReason');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        if (textarea) {
            textarea.value = '';
        }
    }

    // API functions
    async approveTopup(topupId) {
        try {
            const response = await fetch('/api/api/approve_topup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ topup_id: topupId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('تم قبول طلب الشحن بنجاح', 'success');
                this.loadTopups();
                this.loadDashboardData();
            } else {
                this.showNotification('فشل في قبول طلب الشحن: ' + (data.error || data.message), 'error');
            }
        } catch (error) {
            console.error('Error approving topup:', error);
            this.showNotification('حدث خطأ في قبول طلب الشحن', 'error');
        }
    }

    async rejectTopup(topupId) {
        try {
            const response = await fetch('/api/api/reject_topup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ topup_id: topupId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('تم رفض طلب الشحن', 'success');
                this.loadTopups();
                this.loadDashboardData();
            } else {
                this.showNotification('فشل في رفض طلب الشحن: ' + (data.error || data.message), 'error');
            }
        } catch (error) {
            console.error('Error rejecting topup:', error);
            this.showNotification('حدث خطأ في رفض طلب الشحن', 'error');
        }
    }

    async approveWithdraw(withdrawId) {
        try {
            const response = await fetch('/api/api/approve_withdraw.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ withdraw_id: withdrawId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('تم قبول طلب السحب بنجاح', 'success');
                this.loadWithdrawals();
                this.loadDashboardData();
            } else {
                this.showNotification('فشل في قبول طلب السحب: ' + (data.error || data.message), 'error');
            }
        } catch (error) {
            console.error('Error approving withdrawal:', error);
            this.showNotification('حدث خطأ في قبول طلب السحب', 'error');
        }
    }

    async rejectWithdraw(withdrawId) {
        try {
            const response = await fetch('/api/api/reject_withdraw.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ withdraw_id: withdrawId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('تم رفض طلب السحب', 'success');
                this.loadWithdrawals();
                this.loadDashboardData();
            } else {
                this.showNotification('فشل في رفض طلب السحب: ' + (data.error || data.message), 'error');
            }
        } catch (error) {
            console.error('Error rejecting withdrawal:', error);
            this.showNotification('حدث خطأ في رفض طلب السحب', 'error');
        }
    }

    // تم إزالة دالة الاعتماد حسب طلب المستخدم

    async confirmReject() {
        if (!this.currentDealId) return;
        
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) {
            this.showNotification('يرجى كتابة سبب الرفض', 'error');
            return;
        }
        
        // منع التنفيذ المتكرر للطلبات الحساسة
        if (this.isProcessingReject) {
            this.showNotification('جاري معالجة الطلب، يرجى الانتظار...', 'info');
            return;
        }
        
        this.isProcessingReject = true;
        
        // تعطيل زر الرفض أثناء المعالجة
        const rejectButton = document.getElementById('confirmReject');
        if (rejectButton) {
            rejectButton.disabled = true;
            rejectButton.textContent = 'جاري المعالجة...';
        }
        
        try {
            const response = await fetch('/api/api/admin_deals.php?action=reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    deal_id: this.currentDealId,
                    reason: reason
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('تم رفض الصفقة', 'success');
                this.closeRejectModal();
                this.closeDealModal();
                this.loadDeals();
                this.loadDashboardData();
            } else {
                this.showNotification('فشل في رفض الصفقة: ' + (data.error || data.message || 'خطأ غير معروف'), 'error');
            }
        } catch (error) {
            console.error('Error rejecting deal:', error);
            this.showNotification('حدث خطأ في رفض الصفقة', 'error');
        } finally {
            // إعادة تفعيل الزر وإعادة تعيين الحالة
            this.isProcessingReject = false;
            if (rejectButton) {
                rejectButton.disabled = false;
                rejectButton.textContent = 'رفض الصفقة';
            }
        }
    }

    async approveDeal() {
        if (!this.currentDealId) return;
        
        // منع التنفيذ المتكرر للطلبات الحساسة
        if (this.isProcessingApprove) {
            this.showNotification('جاري معالجة الطلب، يرجى الانتظار...', 'info');
            return;
        }
        
        this.isProcessingApprove = true;
        
        // تعطيل زر القبول أثناء المعالجة
        const approveButton = document.getElementById('approveDeal');
        if (approveButton) {
            approveButton.disabled = true;
            approveButton.textContent = 'جاري المعالجة...';
        }
        
        try {
            const response = await fetch('/api/api/admin_deals.php?action=approve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    deal_id: this.currentDealId
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.showNotification('تم قبول الصفقة وتحويل الأموال بنجاح', 'success');
                this.closeDealModal();
                this.loadDeals();
                this.loadDashboardData();
            } else {
                this.showNotification('فشل في قبول الصفقة: ' + (data.error || data.message || 'خطأ غير معروف'), 'error');
            }
        } catch (error) {
            console.error('Error approving deal:', error);
            this.showNotification('حدث خطأ في قبول الصفقة', 'error');
        } finally {
            // إعادة تفعيل الزر وإعادة تعيين الحالة
            this.isProcessingApprove = false;
            if (approveButton) {
                approveButton.disabled = false;
                approveButton.innerHTML = '<i class="fas fa-check ml-1"></i> قبول';
            }
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg text-white max-w-sm ${
            type === 'success' ? 'bg-neon-green' :
            type === 'error' ? 'bg-red-600' :
            'bg-neon-blue'
        }`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const desktopToggleIcon = document.querySelector('#desktopSidebarToggle i');
        
        this.sidebarCollapsed = !this.sidebarCollapsed;
        
        if (this.sidebarCollapsed) {
            sidebar.classList.add('sidebar-collapsed');
            mainContent.classList.add('main-content-expanded');
            if (desktopToggleIcon) {
                desktopToggleIcon.classList.remove('fa-chevron-right');
                desktopToggleIcon.classList.add('fa-chevron-left');
            }
        } else {
            sidebar.classList.remove('sidebar-collapsed');
            mainContent.classList.remove('main-content-expanded');
            if (desktopToggleIcon) {
                desktopToggleIcon.classList.remove('fa-chevron-left');
                desktopToggleIcon.classList.add('fa-chevron-right');
            }
        }
        
        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
        
        // Trigger resize event to adjust charts and tables
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 300);
    }

    adjustTableResponsiveness() {
        const tables = document.querySelectorAll('.table-responsive');
        const isMobile = window.innerWidth < 768;
        
        tables.forEach(table => {
            const headers = table.querySelectorAll('th');
            const cells = table.querySelectorAll('td');
            
            if (isMobile) {
                headers.forEach(header => {
                    header.style.fontSize = '0.75rem';
                    header.style.padding = '0.5rem 0.25rem';
                });
                cells.forEach(cell => {
                    cell.style.fontSize = '0.75rem';
                    cell.style.padding = '0.5rem 0.25rem';
                });
            } else {
                headers.forEach(header => {
                    header.style.fontSize = '';
                    header.style.padding = '';
                });
                cells.forEach(cell => {
                    cell.style.fontSize = '';
                    cell.style.padding = '';
                });
            }
        });
    }

    // Conversation Modal Methods
    async openConversationModal(dealId) {
        this.currentDealId = dealId;
        const modal = document.getElementById('conversationModal');
        const loadingDiv = document.getElementById('loadingMessages');
        const noMessagesDiv = document.getElementById('noMessages');
        const messagesContainer = document.getElementById('messagesContainer');
        
        // Show modal and loading state
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        loadingDiv.classList.remove('hidden');
        noMessagesDiv.classList.add('hidden');
        
        // Clear previous messages completely
        messagesContainer.innerHTML = `
            <div id="loadingMessages" class="text-center text-gray-400 py-8">
                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                <div>جاري تحميل المحادثة...</div>
            </div>
            <div id="noMessages" class="text-center text-gray-400 py-8 hidden">
                <i class="fas fa-comments text-4xl mb-4 opacity-50"></i>
                <div>لا توجد رسائل في هذه المحادثة</div>
            </div>
        `;
        
        try {
            const response = await fetch(`api/get_deal_conversation.php?deal_id=${dealId}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayConversationData(data);
            } else {
                this.showNotification('فشل في تحميل المحادثة: ' + data.message, 'error');
                this.closeConversationModal();
            }
        } catch (error) {
            console.error('Error loading conversation:', error);
            this.showNotification('حدث خطأ في تحميل المحادثة', 'error');
            this.closeConversationModal();
        }
    }

    displayConversationData(data) {
        const loadingDiv = document.getElementById('loadingMessages');
        const noMessagesDiv = document.getElementById('noMessages');
        const messagesContainer = document.getElementById('messagesContainer');
        
        // Hide loading
        if (loadingDiv) loadingDiv.classList.add('hidden');
        
        // Update deal info
        document.getElementById('conversationDealInfo').innerHTML = `
            <span class="text-neon-blue">صفقة #${data.deal.id}</span> - 
            <span class="text-neon-green">${data.deal.game_name}</span> - 
            <span class="text-neon-orange">${data.deal.amount} ج.م</span>
        `;
        
        // Update stats
        document.getElementById('totalMessages').textContent = data.stats.total_messages;
        document.getElementById('buyerMessages').textContent = data.stats.buyer_messages;
        document.getElementById('sellerMessages').textContent = data.stats.seller_messages;
        
        // Calculate and display conversation duration
        if (data.stats.first_message_date && data.stats.last_message_date) {
            const firstDate = new Date(data.stats.first_message_date);
            const lastDate = new Date(data.stats.last_message_date);
            const diffDays = Math.ceil((lastDate - firstDate) / (1000 * 60 * 60 * 24));
            document.getElementById('conversationDuration').textContent = diffDays > 0 ? `${diffDays} يوم` : 'أقل من يوم';
        } else {
            document.getElementById('conversationDuration').textContent = '-';
        }
        
        // Update participants info
        document.getElementById('participantsInfo').innerHTML = `
            المشاركون: 
            <span class="text-neon-green">${data.deal.buyer_name}</span> (مشتري) و 
            <span class="text-neon-purple">${data.deal.seller_name}</span> (بائع)
        `;
        
        // Display messages
        if (data.messages && data.messages.length > 0) {
            this.displayMessages(data.messages, data.deal.buyer_id, data.deal.seller_id);
        } else {
            noMessagesDiv.classList.remove('hidden');
        }
    }

    displayMessages(messages, buyerId, sellerId) {
        const messagesContainer = document.getElementById('messagesContainer');
        
        // Hide loading and no messages divs
        const loadingDiv = document.getElementById('loadingMessages');
        const noMessagesDiv = document.getElementById('noMessages');
        if (loadingDiv) loadingDiv.classList.add('hidden');
        if (noMessagesDiv) noMessagesDiv.classList.add('hidden');
        
        messages.forEach(message => {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message-item mb-3';
            
            const isBuyer = message.sender_id == buyerId;
            const senderRole = isBuyer ? 'مشتري' : 'بائع';
            const senderColor = isBuyer ? 'text-neon-green' : 'text-neon-purple';
            const bgColor = isBuyer ? 'bg-neon-green/10 border-neon-green/30' : 'bg-neon-purple/10 border-neon-purple/30';
            const alignClass = isBuyer ? 'mr-auto' : 'ml-auto';
            const maxWidth = 'max-w-[80%]';
            
            const messageDate = new Date(message.created_at);
            const formattedDate = messageDate.toLocaleString('ar-SA', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            messageDiv.innerHTML = `
                <div class="${alignClass} ${maxWidth}">
                    <div class="${bgColor} border rounded-lg p-4 break-words message-content">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center gap-2">
                                <span class="${senderColor} font-semibold text-sm">${senderRole}</span>
                                <span class="text-xs text-gray-500">${formattedDate}</span>
                            </div>
                        </div>
                        <div class="text-white text-sm leading-relaxed word-wrap break-words message-text">${this.escapeHtml(message.message_text)}</div>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
        });
        
        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    closeConversationModal() {
        const modal = document.getElementById('conversationModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        // Clear search
        document.getElementById('messageSearch').value = '';
        // Don't clear currentDealId here - keep it so we can reopen the conversation
        // It will be cleared when the deal modal is closed
    }

    setupConversationModalEvents() {
        // Close modal events
        document.getElementById('closeConversationModal').addEventListener('click', () => {
            this.closeConversationModal();
        });
        
        document.getElementById('closeConversationModalBtn').addEventListener('click', () => {
            this.closeConversationModal();
        });
        
        // Approve from conversation modal
        document.getElementById('approveFromConversation').addEventListener('click', () => {
            this.closeConversationModal();
            this.approveDeal();
        });
        
        // Reject from conversation modal
        document.getElementById('rejectFromConversation').addEventListener('click', () => {
            this.closeConversationModal();
            this.showRejectModal();
        });
        
        // Search functionality
        document.getElementById('messageSearch').addEventListener('input', (e) => {
            this.filterMessages(e.target.value);
        });
        
        // Close modal when clicking outside
        document.getElementById('conversationModal').addEventListener('click', (e) => {
            if (e.target.id === 'conversationModal') {
                this.closeConversationModal();
            }
        });
    }

    filterMessages(searchTerm) {
        const messages = document.querySelectorAll('.message-item');
        const term = searchTerm.toLowerCase().trim();
        
        messages.forEach(message => {
            const messageText = message.textContent.toLowerCase();
            if (term === '' || messageText.includes(term)) {
                message.style.display = 'block';
            } else {
                message.style.display = 'none';
            }
        });
    }

    async banUser(userId) {
        // Prevent multiple clicks
        if (this.banInProgress) {
            return;
        }
        
        if (!confirm('هل أنت متأكد من حظر هذا المستخدم؟')) {
            return;
        }

        this.banInProgress = true;
        
        try {
            const response = await fetch('/api/api/ban_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_id: userId })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('تم حظر المستخدم بنجاح', 'success');
                this.loadUsers(); // Reload users list
            } else {
                this.showNotification(result.message || 'حدث خطأ أثناء حظر المستخدم', 'error');
            }
        } catch (error) {
            console.error('Error banning user:', error);
            this.showNotification('حدث خطأ أثناء حظر المستخدم', 'error');
        } finally {
            this.banInProgress = false;
        }
    }

    async unbanUser(userId) {
        // Prevent multiple clicks
        if (this.unbanInProgress) {
            return;
        }
        
        if (!confirm('هل أنت متأكد من إلغاء حظر هذا المستخدم؟')) {
            return;
        }

        this.unbanInProgress = true;

        try {
            const response = await fetch('/api/api/unban_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_id: userId })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('تم إلغاء حظر المستخدم بنجاح', 'success');
                this.loadUsers(); // Reload users list
            } else {
                this.showNotification(result.message || 'حدث خطأ أثناء إلغاء حظر المستخدم', 'error');
            }
        } catch (error) {
            console.error('Error unbanning user:', error);
            this.showNotification('حدث خطأ أثناء إلغاء حظر المستخدم', 'error');
        } finally {
            this.unbanInProgress = false;
        }
    }

    async banUserFromReport(userId, userType) {
        // Prevent multiple clicks
        if (this.banInProgress) {
            return;
        }
        
        if (!confirm(`هل أنت متأكد من حظر ${userType}؟`)) {
            return;
        }

        this.banInProgress = true;

        try {
            const response = await fetch('/api/api/ban_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_id: userId })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(`تم حظر ${userType} بنجاح`, 'success');
                this.loadReports(); // Reload reports list
            } else {
                this.showNotification(result.message || `حدث خطأ أثناء حظر ${userType}`, 'error');
            }
        } catch (error) {
            console.error('Error banning user from report:', error);
            this.showNotification(`حدث خطأ أثناء حظر ${userType}`, 'error');
        } finally {
            this.banInProgress = false;
        }
    }

    async viewUserDetails(userId) {
        try {
            const response = await fetch(`api/get_user_details.php?user_id=${userId}`);
            const result = await response.json();
            
            if (result.success) {
                this.showUserDetailsModal(result.user);
            } else {
                this.showNotification(result.message || 'حدث خطأ أثناء جلب تفاصيل المستخدم', 'error');
            }
        } catch (error) {
            console.error('Error fetching user details:', error);
            this.showNotification('حدث خطأ أثناء جلب تفاصيل المستخدم', 'error');
        }
    }

    showUserDetailsModal(user) {
        // Create modal HTML
        const modalHTML = `
            <div id="userDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-gray-800 rounded-xl p-6 max-w-md w-full max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-neon-blue">تفاصيل المستخدم</h3>
                        <button onclick="this.closest('#userDetailsModal').remove()" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-gray-400">الاسم:</label>
                            <p class="text-white">${user.name}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">رقم الهاتف:</label>
                            <p class="text-white">${user.phone}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">العمر:</label>
                            <p class="text-white">${user.age || 'غير محدد'}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">الجنس:</label>
                            <p class="text-white">${user.gender === 'male' ? 'ذكر' : user.gender === 'female' ? 'أنثى' : 'غير محدد'}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">الحالة:</label>
                            <span class="px-2 py-1 rounded-full text-xs ${
                                user.role === 'user' ? 'bg-neon-green/20 text-neon-green' : 
                                user.role === 'banned' ? 'bg-red-500/20 text-red-400' : 'bg-gray-500/20 text-gray-400'
                            }">
                                ${user.role === 'user' ? 'نشط' : user.role === 'banned' ? 'محظور' : user.role === 'admin' ? 'مدير' : 'غير نشط'}
                            </span>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">الرصيد:</label>
                            <p class="text-white">${user.wallet_balance || 0} ج.م</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">حالة الاتصال:</label>
                            <span class="px-2 py-1 rounded-full text-xs ${
                                user.is_online ? 'bg-neon-green/20 text-neon-green' : 'bg-gray-500/20 text-gray-400'
                            }">
                                ${user.is_online ? 'متصل' : 'غير متصل'}
                            </span>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">عدد الصفقات:</label>
                            <p class="text-white">${user.deals_count || 0}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">عدد الإيداعات:</label>
                            <p class="text-white">${user.topups_count || 0}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-400">إجمالي الإيداعات:</label>
                            <p class="text-white">${user.total_topups || 0} ج.م</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Reports Management Functions
    async showReportsSection() {
        const section = document.getElementById('reportsSection');
        if (section) {
            section.style.display = 'block';
            await this.loadReports();
        }
    }

    async loadReports() {
        try {
            const response = await fetch('/api/api/get_reports.php');
            const data = await response.json();
            
            if (data.success && data.data) {
                this.renderReports(data.data.reports || []);
                this.updateReportsStats(data.data.statistics || {});
            } else {
                this.showNotification(data.message || 'حدث خطأ أثناء جلب البلاغات', 'error');
            }
        } catch (error) {
            console.error('Error loading reports:', error);
            this.showNotification('حدث خطأ أثناء جلب البلاغات', 'error');
        }
    }

    renderReports(reports) {
        const tbody = document.getElementById('reportsTableBody');
        if (!tbody) return;

        tbody.innerHTML = reports.map(report => `
            <tr class="border-b border-gray-700">
                <td class="px-3 lg:px-6 py-4" data-label="رقم البلاغ">#${report.id}</td>
                <td class="px-3 lg:px-6 py-4" data-label="المبلغ">${report.reporter?.name || 'غير محدد'}</td>
                <td class="px-3 lg:px-6 py-4" data-label="المحادثة">#${report.conversation_id}</td>
                <td class="px-3 lg:px-6 py-4" data-label="السبب">${report.reason}</td>
                <td class="px-3 lg:px-6 py-4" data-label="الحالة">
                    <span class="status-badge px-2 py-1 rounded-full text-xs ${
                        report.status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                        report.status === 'resolved' ? 'bg-neon-green/20 text-neon-green' :
                        'bg-red-500/20 text-red-400'
                    }">
                        ${
                            report.status === 'pending' ? 'قيد المراجعة' :
                            report.status === 'resolved' ? 'تم الحل' : 'مرفوض'
                        }
                    </span>
                </td>
                <td class="px-3 lg:px-6 py-4" data-label="التاريخ">${new Date(report.created_at).toLocaleDateString('ar-EG')}</td>
                <td class="px-3 lg:px-6 py-4" data-label="الإجراءات">
                    <div class="flex gap-2">
                        <button onclick="adminDashboard.viewReportDetails(${report.id})" 
                                class="bg-neon-blue/20 text-neon-blue px-3 py-1 rounded-lg text-sm hover:bg-neon-blue/30 transition-colors">
                            <i class="fas fa-eye"></i> عرض
                        </button>
                        ${report.status === 'pending' ? `
                            <button onclick="adminDashboard.resolveReport(${report.id})" 
                                    class="bg-neon-green/20 text-neon-green px-3 py-1 rounded-lg text-sm hover:bg-neon-green/30 transition-colors">
                                <i class="fas fa-check"></i> حل
                            </button>
                            <button onclick="adminDashboard.rejectReport(${report.id})" 
                                    class="bg-red-500/20 text-red-400 px-3 py-1 rounded-lg text-sm hover:bg-red-500/30 transition-colors">
                                <i class="fas fa-times"></i> رفض
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    updateReportsStats(stats) {
        const totalElement = document.getElementById('totalReports');
        const pendingElement = document.getElementById('pendingReports');
        const resolvedElement = document.getElementById('resolvedReports');
        
        if (totalElement) totalElement.textContent = stats.total || 0;
        if (pendingElement) pendingElement.textContent = stats.pending || 0;
        if (resolvedElement) resolvedElement.textContent = stats.resolved || 0;
    }

    filterReports(status) {
        const tableBody = document.getElementById('reportsTableBody');
        if (!tableBody) return;
        
        const rows = tableBody.querySelectorAll('tr');
        
        rows.forEach(row => {
            if (status === 'all') {
                row.style.display = '';
            } else {
                const statusCell = row.querySelector('.status-badge');
                if (statusCell) {
                    const rowStatus = statusCell.textContent.trim();
                    const shouldShow = 
                        (status === 'pending' && rowStatus === 'قيد المراجعة') ||
                        (status === 'resolved' && rowStatus === 'تم الحل') ||
                        (status === 'rejected' && rowStatus === 'مرفوض');
                    
                    row.style.display = shouldShow ? '' : 'none';
                }
            }
        });
    }

    async viewReportDetails(reportId) {
        try {
            console.log('📋 Loading report details for ID:', reportId);
            const response = await fetch(`/api/get_reports.php?report_id=${reportId}`);
            const data = await response.json();
            console.log('📦 Report details data:', data);

            if (data.success && data.data && data.data.reports && data.data.reports.length > 0) {
                console.log('✅ Report found:', data.data.reports[0]);
                this.showReportDetailsModal(data.data.reports[0]);
            } else {
                console.error('❌ Report not found or error:', data);
                this.showNotification(data.message || 'حدث خطأ أثناء جلب تفاصيل البلاغ', 'error');
            }
        } catch (error) {
            console.error('💥 Error loading report details:', error);
            this.showNotification('حدث خطأ أثناء جلب تفاصيل البلاغ', 'error');
        }
    }

    showReportDetailsModal(report) {
        const modal = document.getElementById('reportDetailsModal');
        if (!modal) return;
        
        const reportDetailsContent = document.getElementById('reportDetailsContent');
        if (!reportDetailsContent) {
            console.error('Report details content container not found');
            return;
        }
        
        const statusText = report.status === 'pending' ? 'في الانتظار' :
                          report.status === 'under_review' ? 'قيد المراجعة' :
                          report.status === 'resolved' ? 'تم الحل' :
                          report.status === 'dismissed' ? 'مرفوض' : report.status;
        
        reportDetailsContent.innerHTML = `
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-700 p-4 rounded-lg">
                        <h5 class="text-sm font-medium text-gray-300 mb-2">رقم البلاغ</h5>
                        <p class="text-white font-semibold">#${report.id}</p>
                    </div>
                    <div class="bg-gray-700 p-4 rounded-lg">
                        <h5 class="text-sm font-medium text-gray-300 mb-2">حالة البلاغ</h5>
                        <p class="text-white font-semibold">${statusText}</p>
                    </div>
                    <div class="bg-gray-700 p-4 rounded-lg">
                        <h5 class="text-sm font-medium text-gray-300 mb-2">المبلغ</h5>
                        <p class="text-white font-semibold">${report.reporter?.name || report.reporter_name || 'غير محدد'}</p>
                    </div>
                    <div class="bg-gray-700 p-4 rounded-lg">
                        <h5 class="text-sm font-medium text-gray-300 mb-2">المبلغ عنه</h5>
                        <p class="text-white font-semibold">${report.reported_user?.name || report.reported_user_name || 'غير محدد'}</p>
                    </div>
                    <div class="bg-gray-700 p-4 rounded-lg">
                        <h5 class="text-sm font-medium text-gray-300 mb-2">رقم المحادثة</h5>
                        <p class="text-white font-semibold">#${report.conversation_id}</p>
                    </div>
                    <div class="bg-gray-700 p-4 rounded-lg">
                        <h5 class="text-sm font-medium text-gray-300 mb-2">تاريخ البلاغ</h5>
                        <p class="text-white font-semibold">${new Date(report.created_at).toLocaleString('ar-EG')}</p>
                    </div>
                </div>
                <div class="bg-gray-700 p-4 rounded-lg">
                    <h5 class="text-sm font-medium text-gray-300 mb-2">سبب البلاغ</h5>
                    <p class="text-white">${report.reason}</p>
                </div>
                ${report.admin_notes ? `
                <div class="bg-gray-700 p-4 rounded-lg">
                    <h5 class="text-sm font-medium text-gray-300 mb-2">ملاحظات إدارية</h5>
                    <p class="text-white">${report.admin_notes}</p>
                </div>
                ` : ''}
            </div>
            </div>
        `;
        
        // Store current report ID and report data for actions
        this.currentReportId = report.id;
        this.currentReport = report;

        // إخفاء/إظهار زر عرض المحادثة بناءً على وجود conversation_id
        const viewConversationBtn = document.getElementById('viewReportedConversation');
        if (viewConversationBtn) {
            if (!report.conversation_id || report.conversation_id === 0) {
                // إخفاء الزر وإضافة رسالة توضيحية
                viewConversationBtn.style.display = 'none';

                // إضافة رسالة توضيحية بدلاً من الزر
                const conversationContainer = viewConversationBtn.parentElement;
                let noConvMsg = conversationContainer.querySelector('.no-conversation-msg');
                if (!noConvMsg) {
                    noConvMsg = document.createElement('div');
                    noConvMsg.className = 'no-conversation-msg bg-gray-700 text-gray-400 px-4 py-2 rounded-lg text-center text-sm';
                    noConvMsg.innerHTML = '<i class="fas fa-info-circle ml-2"></i>لا توجد محادثة مرتبطة';
                    conversationContainer.insertBefore(noConvMsg, viewConversationBtn);
                }
            } else {
                // إظهار الزر وإزالة الرسالة إذا كانت موجودة
                viewConversationBtn.style.display = 'flex';
                const conversationContainer = viewConversationBtn.parentElement;
                const noConvMsg = conversationContainer.querySelector('.no-conversation-msg');
                if (noConvMsg) {
                    noConvMsg.remove();
                }
            }
        }

        // Show modal
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Add event listener for release funds button
        const releaseFundsBtn = document.getElementById('releaseFundsFromReport');
        if (releaseFundsBtn) {
            releaseFundsBtn.onclick = () => this.releaseFundsFromReport(report);
        }
    }

    async resolveReport(reportId) {
        if (!confirm('هل أنت متأكد من حل هذا البلاغ؟')) {
            return;
        }

        try {
            const response = await fetch('/api/api/admin_reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_status',
                    report_id: reportId,
                    status: 'resolved'
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('تم حل البلاغ بنجاح', 'success');
                this.loadReports(); // Reload reports
                this.closeReportModal();
            } else {
                this.showNotification(result.message || 'حدث خطأ أثناء حل البلاغ', 'error');
            }
        } catch (error) {
            console.error('Error resolving report:', error);
            this.showNotification('حدث خطأ أثناء حل البلاغ', 'error');
        }
    }

    async rejectReport(reportId) {
        if (!confirm('هل أنت متأكد من رفض هذا البلاغ؟')) {
            return;
        }

        try {
            const response = await fetch('/api/api/admin_reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_status',
                    report_id: reportId,
                    status: 'dismissed'
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('تم رفض البلاغ', 'success');
                this.loadReports(); // Reload reports
                this.closeReportModal();
            } else {
                this.showNotification(result.message || 'حدث خطأ أثناء رفض البلاغ', 'error');
            }
        } catch (error) {
            console.error('Error rejecting report:', error);
            this.showNotification('حدث خطأ أثناء رفض البلاغ', 'error');
        }
    }

    closeReportModal() {
        const modal = document.getElementById('reportDetailsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // عرض المحادثة المبلغ عنها
    async viewReportedConversation(reportId) {
        console.log('🚀 viewReportedConversation called!');
        console.log('📋 Current Report:', this.currentReport);
        console.log('💬 Conversation ID:', this.currentReport?.conversation_id);

        if (!this.currentReport || !this.currentReport.conversation_id || this.currentReport.conversation_id === 0) {
            const convId = this.currentReport?.conversation_id || 'null';
            this.showNotification(`❌ لا توجد محادثة مرتبطة بهذا البلاغ (ID: ${convId})`, 'error');
            console.warn('⚠️ Cannot view conversation - invalid conversation_id:', convId);
            return;
        }

        try {
            console.log('🔍 Fetching conversation for ID:', this.currentReport.conversation_id);
            const response = await fetch('/api/api/admin_reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'get_conversation_messages',
                    conversation_id: this.currentReport.conversation_id
                })
            });

            console.log('📡 Response status:', response.status);
            const result = await response.json();
            console.log('📦 Result:', result);

            if (result.success) {
                console.log('✅ Calling showConversationModal with data:', result.data);
                this.showConversationModal(result.data);
            } else {
                console.error('❌ API returned error:', result.message);
                this.showNotification(result.message || 'حدث خطأ أثناء جلب المحادثة', 'error');
            }
        } catch (error) {
            console.error('💥 Error fetching conversation:', error);
            this.showNotification('حدث خطأ أثناء جلب المحادثة', 'error');
        }
    }

    // عرض modal المحادثة
    showConversationModal(conversationData) {
        console.log('🎬 showConversationModal called with:', conversationData);
        const modal = document.getElementById('reportConversationModal');
        if (!modal) {
            console.error('❌ Report conversation modal not found in DOM!');
            return;
        }
        console.log('✅ Modal found:', modal);

        const modalContent = document.getElementById('reportConversationModalContent');
        if (!modalContent) {
            console.error('❌ Report conversation modal content not found in DOM!');
            return;
        }
        console.log('✅ Modal content found:', modalContent);

        const conversation = conversationData.conversation;
        const messages = conversationData.messages;
        console.log('💬 Conversation:', conversation);
        console.log('📨 Messages count:', messages?.length);

        modalContent.innerHTML = `
            <div class="bg-black/90 backdrop-blur-xl border border-white/20 rounded-xl shadow-2xl p-6 max-w-4xl w-full max-h-[85vh] flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-neon-blue to-neon-purple flex items-center justify-center">
                            <i class="fas fa-comments text-black text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold bg-gradient-to-r from-neon-blue to-neon-purple bg-clip-text text-transparent">المحادثة المبلغ عنها</h3>
                            <p class="text-sm text-white/60">عرض المحادثة للمراجعة</p>
                        </div>
                    </div>
                    <button onclick="adminDashboard.closeReportConversationModal()" class="p-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200">
                        <i class="fas fa-times text-neon-blue"></i>
                    </button>
                </div>
                
                <div class="mb-4 p-4 bg-gradient-to-r from-white/5 to-white/10 border border-white/10 rounded-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-neon-blue to-blue-500 flex items-center justify-center">
                                <i class="fas fa-user text-xs text-white"></i>
                            </div>
                            <span class="text-neon-blue font-semibold">${conversation.user1.name}</span>
                        </div>
                        <div class="flex items-center gap-2 text-white/60">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="text-xs">محادثة #${conversation.id}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-neon-purple font-semibold">${conversation.user2.name}</span>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-neon-purple to-purple-500 flex items-center justify-center">
                                <i class="fas fa-user text-xs text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- إحصائيات المحادثة -->
                <div class="mb-4 p-3 bg-gray-800/50 border border-gray-700 rounded-lg">
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 text-center">
                        <div class="bg-gray-700 rounded-lg p-2">
                            <div class="text-neon-blue text-lg font-bold">${messages.length}</div>
                            <div class="text-xs text-gray-400">إجمالي الرسائل</div>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-2">
                            <div class="text-neon-green text-lg font-bold">${messages.filter(m => m.sender_id === conversation.user1.id).length}</div>
                            <div class="text-xs text-gray-400">رسائل ${conversation.user1.name}</div>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-2">
                            <div class="text-neon-purple text-lg font-bold">${messages.filter(m => m.sender_id === conversation.user2.id).length}</div>
                            <div class="text-xs text-gray-400">رسائل ${conversation.user2.name}</div>
                        </div>
                    </div>
                </div>
                
                <div id="conversationMessages" class="flex-1 space-y-4 overflow-y-auto pr-2" style="max-height: 400px;">
                    ${messages.length > 0 ? messages.map(message => {
                        const isUser1 = message.sender_id === conversation.user1.id;
                        return `
                            <div class="flex ${isUser1 ? 'justify-start' : 'justify-end'}">
                                <div class="max-w-xs lg:max-w-md">
                                    <div class="flex items-center gap-2 mb-1 ${isUser1 ? 'justify-start' : 'justify-end'}">
                                        ${isUser1 ? `<div class="w-6 h-6 rounded-full bg-gradient-to-r from-neon-blue to-blue-500 flex items-center justify-center">
                                            <i class="fas fa-user text-xs text-white"></i>
                                        </div>` : ''}
                                        <span class="text-xs font-medium ${isUser1 ? 'text-neon-blue' : 'text-neon-purple'}">
                                            ${message.sender_name}
                                        </span>
                                        ${!isUser1 ? `<div class="w-6 h-6 rounded-full bg-gradient-to-r from-neon-purple to-purple-500 flex items-center justify-center">
                                            <i class="fas fa-user text-xs text-white"></i>
                                        </div>` : ''}
                                    </div>
                                    <div class="px-4 py-3 rounded-xl shadow-lg ${
                                        isUser1 
                                            ? 'bg-gradient-to-r from-neon-blue/20 to-blue-600/20 border border-neon-blue/30 text-white' 
                                            : 'bg-gradient-to-r from-neon-purple/20 to-purple-600/20 border border-neon-purple/30 text-white'
                                    }">
                                        <div class="text-sm leading-relaxed">${this.escapeHtml(message.message_text)}</div>
                                        <div class="text-xs text-white/40 mt-2">${new Date(message.created_at).toLocaleString('ar-EG')}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('') : '<div class="text-center text-white/60 py-8"><i class="fas fa-comments text-2xl mb-2"></i><br>لا توجد رسائل في هذه المحادثة<br><span class="text-xs">قد تكون المحادثة فارغة أو محذوفة</span></div>'}
                </div>
                
                <div class="bg-white/5 border border-white/10 rounded-xl p-4 mt-4">
                    <div class="flex items-center justify-center gap-2 text-white/60">
                        <i class="fas fa-info-circle"></i>
                        <span class="text-sm">هذه محادثة للعرض فقط - تم الإبلاغ عنها للمراجعة</span>
                    </div>
                </div>
            </div>
        `;

        // إغلاق أي modal آخر مفتوح
        console.log('🔄 Closing other modals...');
        this.closeReportModal();
        this.closeAdminChatModal();

        console.log('👁️ Showing modal...');
        console.log('Before - hidden:', modal.classList.contains('hidden'), 'flex:', modal.classList.contains('flex'));
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        console.log('After - hidden:', modal.classList.contains('hidden'), 'flex:', modal.classList.contains('flex'));

        // التمرير لآخر رسالة
        setTimeout(() => {
            const messagesContainer = document.getElementById('conversationMessages');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                console.log('📜 Scrolled to bottom');
            }
        }, 100);

        console.log('✨ Modal should be visible now!');
    }

    // إغلاق modal المحادثة للتقارير
    closeReportConversationModal() {
        const modal = document.getElementById('reportConversationModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // فتح محادثة إدارية مع المستخدم
    async openAdminChat(userId, userType) {
        if (!userId || userId === 'null') {
            this.showNotification('معرف المستخدم غير صحيح', 'error');
            return;
        }

        try {
            const response = await fetch('/api/api/admin_reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_or_create_admin_chat',
                    user_id: userId,
                    user_type: userType
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showAdminChatModal(result.data, userType);
            } else {
                this.showNotification(result.message || 'حدث خطأ أثناء فتح المحادثة', 'error');
            }
        } catch (error) {
            console.error('Error opening admin chat:', error);
            this.showNotification('حدث خطأ أثناء فتح المحادثة', 'error');
        }
    }

    // عرض modal المحادثة الإدارية
    showAdminChatModal(chatData, userType) {
        const modal = document.getElementById('adminChatModal');
        if (!modal) {
            console.error('Admin chat modal not found');
            return;
        }

        const modalContent = document.getElementById('adminChatModalContent');
        if (!modalContent) {
            console.error('Admin chat modal content not found');
            return;
        }

        const userTypeText = userType === 'reporter' ? 'المبلغ' : 'المبلغ عنه';
        const messages = chatData.messages || [];

        modalContent.innerHTML = `
            <div class="bg-black/90 backdrop-blur-xl border border-white/20 rounded-xl shadow-2xl p-6 max-w-4xl w-full max-h-[85vh] flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-neon-purple to-neon-blue flex items-center justify-center">
                            <i class="fas fa-user-shield text-black text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold bg-gradient-to-r from-neon-blue to-neon-purple bg-clip-text text-transparent">محادثة إدارية</h3>
                            <p class="text-sm text-white/60">${userTypeText}: ${chatData.user_name}</p>
                        </div>
                    </div>
                    <button onclick="adminDashboard.closeAdminChatModal()" class="p-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200">
                        <i class="fas fa-times text-neon-blue"></i>
                    </button>
                </div>
                
                <div id="adminChatMessages" class="flex-1 space-y-4 overflow-y-auto mb-6 pr-2" style="max-height: 400px;">
                    ${messages.length > 0 ? messages.map(message => `
                        <div class="flex ${message.is_admin ? 'justify-end' : 'justify-start'}">
                            <div class="max-w-xs lg:max-w-md">
                                <div class="flex items-center gap-2 mb-1 ${message.is_admin ? 'justify-end' : 'justify-start'}">
                                    ${!message.is_admin ? `<div class="w-6 h-6 rounded-full bg-gradient-to-r from-gray-600 to-gray-500 flex items-center justify-center">
                                        <i class="fas fa-user text-xs text-white"></i>
                                    </div>` : ''}
                                    <span class="text-xs font-medium ${message.is_admin ? 'text-neon-green' : 'text-white/60'}">
                                        ${message.is_admin ? 'الإدارة' : chatData.user_name}
                                    </span>
                                    ${message.is_admin ? `<div class="w-6 h-6 rounded-full bg-gradient-to-r from-neon-green to-neon-blue flex items-center justify-center">
                                        <i class="fas fa-shield-halved text-xs text-black"></i>
                                    </div>` : ''}
                                </div>
                                <div class="px-4 py-3 rounded-xl shadow-lg ${
                                    message.is_admin 
                                        ? 'bg-gradient-to-r from-neon-green/20 to-neon-blue/20 border border-neon-green/30 text-white' 
                                        : 'bg-white/10 border border-white/20 text-white'
                                }">
                                    <div class="text-sm leading-relaxed">${this.escapeHtml(message.message_text)}</div>
                                    <div class="text-xs text-white/40 mt-2">${new Date(message.created_at).toLocaleString('ar-EG')}</div>
                                </div>
                            </div>
                        </div>
                    `).join('') : '<div class="text-center text-white/60 py-8"><i class="fas fa-comments text-2xl mb-2"></i><br>لا توجد رسائل بعد<br><span class="text-xs">ابدأ محادثة مع المستخدم</span></div>'}
                </div>
                
                <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                    <div class="flex gap-3">
                        <input type="text" id="adminChatInput" placeholder="اكتب رسالتك هنا..." 
                               class="flex-1 px-4 py-3 bg-white/5 border border-white/10 text-white placeholder-white/60 rounded-xl focus:border-neon-blue/50 focus:bg-white/10 transition-all duration-200 focus:outline-none">
                        <button onclick="adminDashboard.sendAdminMessage(${chatData.user_id})" 
                                class="bg-gradient-to-r from-neon-blue to-neon-purple hover:from-neon-purple hover:to-neon-blue text-white px-6 py-3 rounded-xl transition-all duration-200 font-semibold shadow-lg hover:shadow-neon-blue/25">
                            <i class="fas fa-paper-plane ml-1"></i>إرسال
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Store current chat data
        this.currentAdminChat = chatData;

        // إغلاق أي modal آخر مفتوح
        this.closeReportModal();
        this.closeConversationModal();

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // التمرير لآخر رسالة
        setTimeout(() => {
            const messagesContainer = document.getElementById('adminChatMessages');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            // Focus on input
            const input = document.getElementById('adminChatInput');
            if (input) {
                input.focus();
                
                // إضافة مستمع للضغط على Enter
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendAdminMessage(chatData.user_id);
                    }
                });
            }
        }, 100);
    }

    // إغلاق modal المحادثة الإدارية
    closeAdminChatModal() {
        const modal = document.getElementById('adminChatModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // إرسال رسالة إدارية
    async sendAdminMessage(userId) {
        const input = document.getElementById('adminChatInput');
        if (!input) return;

        const message = input.value.trim();
        if (!message) {
            this.showNotification('يرجى كتابة رسالة', 'error');
            return;
        }

        // تعطيل الزر أثناء الإرسال
        const sendButton = input.nextElementSibling;
        const originalButtonContent = sendButton.innerHTML;
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i>جاري الإرسال...';
        sendButton.classList.add('opacity-50', 'cursor-not-allowed');

        try {
            const response = await fetch('/api/api/admin_reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'send_admin_message',
                    user_id: userId,
                    message: message
                })
            });

            const result = await response.json();
            
            if (result.success) {
                // Clear input
                input.value = '';
                
                // Add message to chat with animation
                const messagesContainer = document.getElementById('adminChatMessages');
                if (messagesContainer) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'flex justify-end opacity-0 transform translate-y-4 transition-all duration-300';
                    messageDiv.innerHTML = `
                        <div class="max-w-xs lg:max-w-md">
                            <div class="flex items-center gap-2 mb-1 justify-end">
                                <span class="text-xs font-medium text-neon-green">الإدارة</span>
                                <div class="w-6 h-6 rounded-full bg-gradient-to-r from-neon-green to-neon-blue flex items-center justify-center">
                                    <i class="fas fa-shield-halved text-xs text-black"></i>
                                </div>
                            </div>
                            <div class="px-4 py-3 rounded-xl shadow-lg bg-gradient-to-r from-neon-green/20 to-neon-blue/20 border border-neon-green/30 text-white">
                                <div class="text-sm leading-relaxed">${this.escapeHtml(message)}</div>
                                <div class="text-xs text-white/40 mt-2">${new Date().toLocaleString('ar-EG')}</div>
                            </div>
                        </div>
                    `;
                    
                    messagesContainer.appendChild(messageDiv);
                    
                    // تطبيق الانيميشن
                    setTimeout(() => {
                        messageDiv.classList.remove('opacity-0', 'translate-y-4');
                        messageDiv.classList.add('opacity-100', 'translate-y-0');
                    }, 50);
                    
                    // التمرير لآخر رسالة
                    setTimeout(() => {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }, 100);
                }
                
                this.showNotification('تم إرسال الرسالة بنجاح', 'success');
            } else {
                this.showNotification(result.message || 'حدث خطأ أثناء إرسال الرسالة', 'error');
            }
        } catch (error) {
            console.error('Error sending admin message:', error);
            this.showNotification('حدث خطأ أثناء إرسال الرسالة', 'error');
        } finally {
            // إعادة تفعيل الزر
            sendButton.disabled = false;
            sendButton.innerHTML = originalButtonContent;
            sendButton.classList.remove('opacity-50', 'cursor-not-allowed');
            input.focus();
        }
    }

    async releaseFundsToSeller(dealId) {
        if (!confirm('هل أنت متأكد من تحرير الأموال للبائع؟ هذا الإجراء لا يمكن التراجع عنه.')) {
            return;
        }
        
        try {
            const response = await fetch('/api/api/admin_deals.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'release_funds_to_seller',
                    deal_id: dealId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification(`تم تحرير الأموال بنجاح. المبلغ للبائع: ${data.seller_amount} ج.م، الرسوم: ${data.fee_amount} ج.م`, 'success');
                // إعادة تحميل قائمة الصفقات
                await this.loadDeals();
            } else {
                this.showNotification(data.error || 'فشل في تحرير الأموال', 'error');
            }
        } catch (error) {
            console.error('Error releasing funds:', error);
            this.showNotification('حدث خطأ أثناء تحرير الأموال', 'error');
        }
    }

    async releaseFundsFromReport(report) {
        if (!report.conversation_id) {
            this.showNotification('لا يوجد محادثة مرتبطة بهذا البلاغ', 'error');
            return;
        }

        try {
            // البحث عن الصفقة المرتبطة بالمحادثة
            const dealResponse = await fetch(`api/admin_deals.php?action=get_deal_by_conversation&conversation_id=${report.conversation_id}`);
        const dealData = await dealResponse.json();
            
            if (!dealData.success || !dealData.deal) {
                this.showNotification('لا يوجد صفقة مرتبطة بهذا البلاغ', 'error');
                return;
            }

            const deal = dealData.deal;
            
            if (!confirm(`هل أنت متأكد من تحرير الأموال للبائع من خلال هذا البلاغ؟\nالصفقة #${deal.id} - المبلغ: ${deal.amount} ج.م\nهذا الإجراء لا يمكن التراجع عنه.`)) {
                return;
            }

            const response = await fetch('/api/api/admin_deals.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'release_funds_to_seller',
                    deal_id: deal.id
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(`تم تحرير الأموال بنجاح من خلال البلاغ. المبلغ للبائع: ${data.seller_amount} ج.م، الرسوم: ${data.fee_amount} ج.م`, 'success');
                // Close the modal
                const modal = document.getElementById('reportDetailsModal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                // Reload reports and deals
                this.loadReports();
                this.loadDeals();
            } else {
                this.showNotification(data.error || 'فشل في تحرير الأموال', 'error');
            }
        } catch (error) {
            console.error('Error releasing funds from report:', error);
            this.showNotification('حدث خطأ أثناء تحرير الأموال', 'error');
        }
    }
}

// Global function for sidebar navigation
function showSection(section) {
    if (window.adminDashboard) {
        window.adminDashboard.showSection(section);
    }
}

// Sidebar management
let sidebarCollapsed = false;

function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const desktopSidebarToggle = document.getElementById('desktopSidebarToggle');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    // Mobile menu toggle
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('sidebar-open');
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('sidebar-open')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
    
    // Mobile sidebar close button
    const mobileSidebarClose = document.getElementById('mobileSidebarClose');
    if (mobileSidebarClose) {
        mobileSidebarClose.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.remove('sidebar-open');
            // Reset mobile menu button icon
            if (mobileMenuBtn) {
                const icon = mobileMenuBtn.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
            // Restore body scroll
            document.body.style.overflow = '';
        });
    }
    
    // Desktop sidebar toggle
    if (desktopSidebarToggle) {
        desktopSidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
    }
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
    }
    
    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 1024) {
            if (!sidebar.contains(e.target) && 
                !mobileMenuBtn?.contains(e.target) &&
                sidebar.classList.contains('sidebar-open')) {
                sidebar.classList.remove('sidebar-open');
                const icon = mobileMenuBtn?.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        }
    });
    
    // Handle window resize with debouncing
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('sidebar-open');
                const icon = mobileMenuBtn?.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
            // Adjust table responsiveness
            adjustTableResponsiveness();
        }, 100);
    });
    
    // Initial table adjustment
    adjustTableResponsiveness();
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const desktopToggleIcon = document.querySelector('#desktopSidebarToggle i');
    
    sidebarCollapsed = !sidebarCollapsed;
    
    if (sidebarCollapsed) {
        sidebar.classList.add('sidebar-collapsed');
        mainContent.classList.add('main-content-expanded');
        if (desktopToggleIcon) {
            desktopToggleIcon.classList.remove('fa-chevron-right');
            desktopToggleIcon.classList.add('fa-chevron-left');
        }
    } else {
        sidebar.classList.remove('sidebar-collapsed');
        mainContent.classList.remove('main-content-expanded');
        if (desktopToggleIcon) {
            desktopToggleIcon.classList.remove('fa-chevron-left');
            desktopToggleIcon.classList.add('fa-chevron-right');
        }
    }
    
    // Save state to localStorage
    localStorage.setItem('sidebarCollapsed', sidebarCollapsed);
    
    // Trigger resize event to adjust charts and tables
    setTimeout(() => {
        window.dispatchEvent(new Event('resize'));
    }, 300);
}

// Adjust table responsiveness based on screen size
function adjustTableResponsiveness() {
    const tables = document.querySelectorAll('.table-responsive');
    const isMobile = window.innerWidth < 768;
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        const cells = table.querySelectorAll('td');
        
        if (isMobile) {
            headers.forEach(header => {
                header.style.fontSize = '0.75rem';
                header.style.padding = '0.5rem 0.25rem';
            });
            cells.forEach(cell => {
                cell.style.fontSize = '0.75rem';
                cell.style.padding = '0.5rem 0.25rem';
            });
        } else {
            headers.forEach(header => {
                header.style.fontSize = '';
                header.style.padding = '';
            });
            cells.forEach(cell => {
                cell.style.fontSize = '';
                cell.style.padding = '';
            });
        }
    });
}

// Global function to close report modal
function closeReportModal() {
    if (window.adminDashboard) {
        window.adminDashboard.closeReportModal();
    }
}

// Global logout function
function logout() {
    if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
        fetch('/api/api/logout.php', {
            method: 'POST',
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'index.html';
            } else {
                alert('حدث خطأ أثناء تسجيل الخروج');
            }
        })
        .catch(error => {
            console.error('Logout error:', error);
            // Redirect anyway since session might be cleared
            window.location.href = 'index.html';
        });
    }
}

// Toggle authentication state
function toggleAuth() {
    fetch('/api/api/check_login.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            if (data.logged_in) {
                // User is logged in, perform logout
                if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                    fetch('/api/api/logout.php', {
                        method: 'POST',
                        credentials: 'include'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update button to show login state
                            updateAuthButton(false);
                            // Redirect to home page
                            setTimeout(() => {
                                window.location.href = 'index.html';
                            }, 500);
                        }
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        window.location.href = 'index.html';
                    });
                }
            } else {
                // User is not logged in, redirect to login page
                window.location.href = 'login.html';
            }
        })
        .catch(error => {
            console.error('Auth check error:', error);
            window.location.href = 'login.html';
        });
}

// Update auth button based on login state
function updateAuthButton(isLoggedIn) {
    const authIcon = document.getElementById('authIcon');
    const authText = document.getElementById('authText');
    const authBtn = document.getElementById('authToggleBtn');

    if (isLoggedIn) {
        if (authIcon) authIcon.className = 'fas fa-sign-out-alt w-4 lg:w-5 flex-shrink-0 text-center';
        if (authText) authText.textContent = 'تسجيل الخروج';
        if (authBtn) authBtn.classList.add('text-neon-red', 'hover:text-red-400');
    } else {
        if (authIcon) authIcon.className = 'fas fa-sign-in-alt w-4 lg:w-5 flex-shrink-0 text-center';
        if (authText) authText.textContent = 'تسجيل دخول';
        if (authBtn) authBtn.classList.remove('text-neon-red', 'hover:text-red-400');
        if (authBtn) authBtn.classList.add('text-neon-green', 'hover:text-green-400');
    }
}

// Check auth state on page load
function checkAuthState() {
    fetch('/api/api/check_login.php', { credentials: 'include' })
        .then(res => res.json())
        .then(data => {
            updateAuthButton(data.logged_in);
        })
        .catch(error => {
            console.error('Auth check error:', error);
            updateAuthButton(false);
        });
}

// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', function() {
    window.adminDashboard = new AdminDashboard();
    window.adminDashboard.init();
    initializeSidebar();
    checkAuthState(); // Check and update auth button state
});