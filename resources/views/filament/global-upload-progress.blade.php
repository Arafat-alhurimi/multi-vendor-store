<div
    x-data="{
        key: 'global-upload-progress',
        uploading: false,
        progress: 0,
        done: false,
        expanded: false,
        currentStatus: '',
        logs: [],
        totalFiles: 0,
        completedFiles: 0,
        hideTimer: null,
        init() {
            const saved = JSON.parse(localStorage.getItem(this.key) || '{}')
            this.uploading = !!saved.uploading
            this.progress = Number.isFinite(saved.progress) ? saved.progress : 0
            this.done = !!saved.done
            this.expanded = !!saved.expanded
            this.currentStatus = typeof saved.currentStatus === 'string' ? saved.currentStatus : ''
            this.logs = Array.isArray(saved.logs) ? saved.logs.slice(-8) : []
            this.totalFiles = Number.isFinite(saved.totalFiles) ? saved.totalFiles : 0
            this.completedFiles = Number.isFinite(saved.completedFiles) ? saved.completedFiles : 0

            if (this.uploading && this.progress < 5) {
                this.progress = 5
            }

            if (this.uploading && this.done) {
                this.scheduleAutoHide()
            }

            window.addEventListener('livewire-upload-start', () => {
                this.clearHideTimer()
                this.uploading = true
                this.done = false
                this.progress = 3
                this.logs = []
                this.totalFiles = 0
                this.completedFiles = 0
                this.currentStatus = 'بدء الرفع...'
                this.pushLog(this.currentStatus)
                this.persist()
            })

            window.addEventListener('s3-upload-start', () => {
                this.clearHideTimer()
                this.uploading = true
                this.done = false
                this.progress = 3
                this.logs = []
                this.totalFiles = 0
                this.completedFiles = 0
                this.currentStatus = 'بدء رفع الوسائط إلى S3...'
                this.pushLog(this.currentStatus)
                this.persist()
            })

            window.addEventListener('s3-upload-counter', (event) => {
                this.totalFiles = Number(event.detail?.total || 0)
                this.completedFiles = Number(event.detail?.completed || 0)
                this.persist()
            })

            window.addEventListener('livewire-upload-progress', (event) => {
                this.uploading = true
                this.progress = Math.max(3, event.detail.progress || 0)
                this.persist()
            })

            window.addEventListener('s3-upload-progress', (event) => {
                this.uploading = true
                this.progress = Math.max(3, event.detail.progress || 0)
                if (event.detail?.status) {
                    this.currentStatus = event.detail.status
                }
                this.persist()
            })

            window.addEventListener('s3-upload-status', (event) => {
                const text = event.detail?.message
                if (!text) {
                    return
                }

                this.currentStatus = text
                this.pushLog(text)
                this.persist()
            })

            window.addEventListener('livewire-upload-error', () => {
                this.uploading = false
                this.done = false
                this.progress = 0
                this.currentStatus = 'حدث خطأ أثناء الرفع'
                this.pushLog(this.currentStatus)
                this.persist()
            })

            window.addEventListener('s3-upload-error', () => {
                this.uploading = false
                this.done = false
                this.progress = 0
                this.currentStatus = 'فشل رفع بعض الملفات'
                this.pushLog(this.currentStatus)
                this.persist()
            })

            window.addEventListener('livewire-upload-finish', () => {
                this.uploading = true
                this.done = true
                this.progress = 100
                this.currentStatus = 'تم الرفع بنجاح'
                this.pushLog(this.currentStatus)
                this.persist()
                this.scheduleAutoHide()
            })

            window.addEventListener('s3-upload-finish', () => {
                this.uploading = true
                this.done = true
                this.progress = 100
                this.currentStatus = 'تم الرفع بنجاح'
                this.pushLog(this.currentStatus)
                this.persist()
                this.scheduleAutoHide()
            })
        },
        scheduleAutoHide() {
            this.clearHideTimer()
            this.hideTimer = setTimeout(() => {
                if (this.expanded) {
                    this.scheduleAutoHide()
                    return
                }

                this.uploading = false
                this.done = false
                this.progress = 0
                this.totalFiles = 0
                this.completedFiles = 0
                this.currentStatus = ''
                this.logs = []
                this.persist()
            }, 10000)
        },
        pushLog(text) {
            this.logs.push(text)
            this.logs = this.logs.slice(-8)
        },
        clearHideTimer() {
            if (this.hideTimer) {
                clearTimeout(this.hideTimer)
                this.hideTimer = null
            }
        },
        toggleExpanded() {
            this.expanded = !this.expanded
            this.persist()
        },
        persist() {
            localStorage.setItem(this.key, JSON.stringify({
                uploading: this.uploading,
                progress: this.progress,
                done: this.done,
                expanded: this.expanded,
                currentStatus: this.currentStatus,
                logs: this.logs,
                totalFiles: this.totalFiles,
                completedFiles: this.completedFiles,
            }))
        },
    }"
    style="pointer-events:none;"
>
    <div
        x-show="uploading"
        x-transition.opacity
        style="display:none; position:fixed; left:16px; bottom:16px; z-index:100001;"
    >
        <div style="pointer-events:auto; width:320px; border:1px solid #d1d5db; border-radius:12px; background:#fff; padding:12px; box-shadow:0 10px 25px rgba(0,0,0,.15); font-size:12px;">
            <button type="button" @click="toggleExpanded()" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; width:100%; background:transparent; border:0; cursor:pointer; padding:0;">
                <span style="font-weight:700; color:#1f2937;" x-text="done ? 'تم الرفع بنجاح' : 'جاري رفع الوسائط...' "></span>
                <span style="font-weight:800; color:#374151;" x-text="progress + '%' "></span>
            </button>
            <div x-show="totalFiles > 0" style="margin-bottom:8px; font-size:11px; color:#4b5563;">
                تم رفع <span style="font-weight:700;" x-text="completedFiles"></span> من <span style="font-weight:700;" x-text="totalFiles"></span>
            </div>
            <div style="height:8px; overflow:hidden; border-radius:9999px; background:#e5e7eb;">
                <div :style="`height:8px; width:${progress}%; background:${done ? '#10b981' : '#2563eb'}; transition:width .2s ease;`"></div>
            </div>

            <div x-show="expanded" x-transition style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:8px;">
                <div style="font-size:11px; color:#374151; margin-bottom:6px;" x-text="currentStatus || 'لا توجد تفاصيل حاليًا' "></div>
                <ul style="max-height:120px; overflow:auto; margin:0; padding-right:16px; list-style:disc; color:#4b5563; font-size:11px;">
                    <template x-for="(item, index) in logs" :key="index">
                        <li x-text="item"></li>
                    </template>
                </ul>
            </div>
        </div>
    </div>
</div>
