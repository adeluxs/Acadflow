<template>
    <div class="notification-center">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Notifications</h3>
            <div class="flex gap-2">
                <button 
                    v-if="unreadCount > 0"
                    @click="markAllRead"
                    class="text-sm text-blue-600 hover:text-blue-800"
                >
                    Mark all read
                </button>
                <button 
                    @click="showFilter = !showFilter"
                    class="text-sm text-gray-600 hover:text-gray-800"
                >
                    {{ showFilter ? 'Hide' : 'Show' }} filters
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div v-if="showFilter" class="mb-4 p-3 bg-gray-50 rounded-lg">
            <div class="flex flex-wrap gap-2">
                <button 
                    v-for="type in notificationTypes" 
                    :key="type.value"
                    @click="toggleFilter(type.value)"
                    :class="[
                        'px-3 py-1 text-sm rounded-full',
                        activeFilters.includes(type.value) 
                            ? 'bg-blue-500 text-white' 
                            : 'bg-gray-200 text-gray-700'
                    ]"
                >
                    {{ type.label }}
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
            <p class="text-gray-500 mt-2">Loading notifications...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredNotifications.length === 0" class="text-center py-8">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <p class="text-gray-500">No notifications</p>
        </div>

        <!-- Notification List -->
        <div v-else class="space-y-2">
            <div 
                v-for="notification in filteredNotifications" 
                :key="notification.id"
                :class="[
                    'p-4 rounded-lg cursor-pointer transition-all',
                    notification.read_at 
                        ? 'bg-white hover:bg-gray-50' 
                        : 'bg-blue-50 hover:bg-blue-100 border-l-4 border-blue-500'
                ]"
                @click="handleNotificationClick(notification)"
            >
                <div class="flex items-start gap-3">
                    <!-- Icon -->
                    <div :class="['flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center', getIconClass(notification.type)]">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path v-if="notification.type === 'submission_received'" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="font-medium text-gray-900 truncate">{{ notification.title }}</p>
                            <span class="text-xs text-gray-500">{{ formatTime(notification.created_at) }}</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ notification.message }}</p>
                        
                        <!-- Action Button -->
                        <div v-if="notification.data?.action_url" class="mt-2">
                            <a :href="notification.data.action_url" class="text-sm text-blue-600 hover:text-blue-800">
                                View details →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Load More -->
        <div v-if="hasMore" class="text-center mt-4">
            <button 
                @click="loadMore"
                :disabled="loadingMore"
                class="px-4 py-2 text-sm text-blue-600 hover:text-blue-800 disabled:opacity-50"
            >
                {{ loadingMore ? 'Loading...' : 'Load more' }}
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'NotificationCenter',
    props: {
        initialNotifications: {
            type: Array,
            default: () => []
        },
        unreadCount: {
            type: Number,
            default: 0
        }
    },
    data() {
        return {
            notifications: this.initialNotifications,
            loading: false,
            loadingMore: false,
            showFilter: false,
            activeFilters: [],
            page: 1,
            hasMore: true,
            notificationTypes: [
                { value: 'submission_received', label: 'Submissions' },
                { value: 'comment_added', label: 'Comments' },
                { value: 'correction_requested', label: 'Corrections' },
                { value: 'grade_posted', label: 'Grades' },
                { value: 'deadline_approaching', label: 'Deadlines' },
                { value: 'attendance_started', label: 'Attendance' },
                { value: 'payment_received', label: 'Payments' },
                { value: 'new_material', label: 'Materials' }
            ]
        };
    },
    computed: {
        filteredNotifications() {
            if (this.activeFilters.length === 0) {
                return this.notifications;
            }
            return this.notifications.filter(n => this.activeFilters.includes(n.type));
        }
    },
    mounted() {
        this.loadNotifications();
        this.setupRealTimeUpdates();
    },
    methods: {
        async loadNotifications() {
            this.loading = true;
            try {
                const response = await axios.get('/api/v1/notifications', {
                    params: { page: this.page, per_page: 20 }
                });
                this.notifications = response.data.data || response.data;
                this.hasMore = response.data.next_page_url !== null;
            } catch (error) {
                console.error('Failed to load notifications:', error);
            } finally {
                this.loading = false;
            }
        },
        async loadMore() {
            this.loadingMore = true;
            this.page++;
            try {
                const response = await axios.get('/api/v1/notifications', {
                    params: { page: this.page, per_page: 20 }
                });
                this.notifications = [...this.notifications, ...(response.data.data || response.data)];
                this.hasMore = response.data.next_page_url !== null;
            } catch (error) {
                console.error('Failed to load more notifications:', error);
            } finally {
                this.loadingMore = false;
            }
        },
        async markAllRead() {
            try {
                await axios.put('/api/v1/notifications/read-all');
                this.notifications = this.notifications.map(n => ({
                    ...n,
                    read_at: new Date().toISOString()
                }));
                this.$emit('update:unreadCount', 0);
            } catch (error) {
                console.error('Failed to mark all as read:', error);
            }
        },
        async handleNotificationClick(notification) {
            if (!notification.read_at) {
                try {
                    await axios.put(`/api/v1/notifications/${notification.id}/read`);
                    notification.read_at = new Date().toISOString();
                    this.$emit('update:unreadCount', this.unreadCount - 1);
                } catch (error) {
                    console.error('Failed to mark as read:', error);
                }
            }
            
            if (notification.data?.url) {
                window.location.href = notification.data.url;
            }
        },
        toggleFilter(type) {
            const index = this.activeFilters.indexOf(type);
            if (index > -1) {
                this.activeFilters.splice(index, 1);
            } else {
                this.activeFilters.push(type);
            }
        },
        getIconClass(type) {
            const classes = {
                'submission_received': 'bg-blue-100 text-blue-600',
                'comment_added': 'bg-green-100 text-green-600',
                'correction_requested': 'bg-yellow-100 text-yellow-600',
                'grade_posted': 'bg-purple-100 text-purple-600',
                'deadline_approaching': 'bg-red-100 text-red-600',
                'attendance_started': 'bg-indigo-100 text-indigo-600',
                'payment_received': 'bg-green-100 text-green-600',
                'new_material': 'bg-gray-100 text-gray-600'
            };
            return classes[type] || 'bg-gray-100 text-gray-600';
        },
        formatTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
            if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
            if (diff < 604800000) return `${Math.floor(diff / 86400000)}d ago`;
            
            return date.toLocaleDateString();
        },
        setupRealTimeUpdates() {
            // Poll for new notifications every 30 seconds
            setInterval(() => {
                this.checkNewNotifications();
            }, 30000);
        },
        async checkNewNotifications() {
            try {
                const response = await axios.get('/api/v1/notifications/unread-count');
                this.$emit('update:unreadCount', response.data.count);
            } catch (error) {
                // Silent fail for polling
            }
        }
    }
};
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>