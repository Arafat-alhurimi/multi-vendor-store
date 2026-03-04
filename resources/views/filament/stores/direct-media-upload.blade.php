<div
    x-data="storeDirectUploader()"
    x-init="init()"
    wire:ignore
    style="display:flex; flex-direction:column; gap:20px; margin:8px 0;"
>
    <div style="border:1px solid #d1d5db; border-radius:12px; padding:16px; background:#f9fafb;">
        <div style="font-size:20px; font-weight:800; color:#111827; margin-bottom:10px;">الشعار</div>
        <div style="display:block; margin-bottom:10px;">
        <label style="display:inline-flex; align-items:center; cursor:pointer; border-radius:8px; background:#2563eb; color:#fff; padding:10px 14px; font-size:14px; font-weight:700;">
            <span>انقر لاختيار الشعار</span>
            <input type="file" accept="image/*" @change="selectLogo($event)" :disabled="isUploading" class="hidden" />
        </label>
        </div>
        <p style="margin-top:6px; font-size:12px; color:#4b5563;" x-text="logoFile ? ('تم اختيار: ' + logoFile.name) : (logoName ? 'تم رفع: ' + logoName : 'لم يتم اختيار شعار بعد')"></p>
    </div>

    <div style="border:1px solid #d1d5db; border-radius:12px; padding:16px; background:#f9fafb;">
        <div style="font-size:20px; font-weight:800; color:#111827; margin-bottom:10px;">الصور</div>
        <div style="display:block; margin-bottom:10px;">
        <label style="display:inline-flex; align-items:center; cursor:pointer; border-radius:8px; background:#2563eb; color:#fff; padding:10px 14px; font-size:14px; font-weight:700;">
            <span>انقر لاختيار الصور</span>
            <input type="file" accept="image/*" multiple @change="selectImages($event)" :disabled="isUploading" class="hidden" />
        </label>
        </div>
        <p style="margin-top:6px; font-size:12px; color:#4b5563;" x-text="pendingImages.length ? ('صور جاهزة للرفع: ' + pendingImages.length) : (imageKeys.length ? ('صور مرفوعة: ' + imageKeys.length) : 'لا توجد صور')"></p>
    </div>

    <div style="border:1px solid #d1d5db; border-radius:12px; padding:16px; background:#f9fafb;">
        <div style="font-size:20px; font-weight:800; color:#111827; margin-bottom:10px;">الفيديو</div>
        <div style="display:block; margin-bottom:10px;">
        <label style="display:inline-flex; align-items:center; cursor:pointer; border-radius:8px; background:#2563eb; color:#fff; padding:10px 14px; font-size:14px; font-weight:700;">
            <span>انقر لاختيار الفيديوهات</span>
            <input type="file" accept="video/mp4,video/quicktime,video/webm" multiple @change="selectVideos($event)" :disabled="isUploading" class="hidden" />
        </label>
        </div>
        <p style="margin-top:6px; font-size:12px; color:#4b5563;" x-text="pendingVideos.length ? ('فيديوهات جاهزة للرفع: ' + pendingVideos.length) : (videoKeys.length ? ('فيديوهات مرفوعة: ' + videoKeys.length) : 'لا توجد فيديوهات')"></p>
        <p style="margin-top:4px; font-size:12px; color:#b45309;">تنبيه: الفيديو الأطول من 60 ثانية يتم رفضه.</p>
    </div>

    <template x-if="failedItems.length">
        <div class="rounded border border-red-200 bg-red-50 p-3">
            <div class="mb-2 text-xs font-semibold text-red-700">فشل رفع بعض الملفات</div>
            <ul class="mb-2 list-disc pr-4 text-xs text-red-700">
                <template x-for="item in failedItems" :key="item.name + item.type">
                    <li x-text="item.name"></li>
                </template>
            </ul>
            <button type="button" @click="retryFailed()" :disabled="isUploading" :style="isUploading ? 'opacity:.6;cursor:not-allowed;' : ''" class="rounded bg-red-600 px-3 py-1 text-xs text-white">إعادة رفع الملفات الفاشلة</button>
        </div>
    </template>

    <template x-if="message">
        <p class="text-xs" :class="isError ? 'text-red-600' : 'text-green-600'" x-text="message"></p>
    </template>

    <div
        x-show="isUploading"
        x-transition.opacity
        style="position:fixed; inset:0; z-index:99999; background:rgba(17,24,39,.45); display:flex; align-items:center; justify-content:center;"
    >
        <div style="background:#fff; border-radius:12px; padding:16px 20px; box-shadow:0 10px 25px rgba(0,0,0,.15); text-align:center; min-width:280px;">
            <div style="font-size:15px; font-weight:800; color:#111827; margin-bottom:6px;">جاري رفع الملفات</div>
            <div style="font-size:12px; color:#4b5563;">الرجاء الانتظار، لا يمكن استخدام الصفحة أثناء الرفع</div>
        </div>
    </div>
</div>

<script>
function storeDirectUploader() {
    return {
        createdStoreId: null,
        isUploading: false,
        statusText: 'بانتظار اختيار الملفات',
        logoName: '',
        logoFile: null,
        imageKeys: [],
        videoKeys: [],
        pendingImages: [],
        pendingVideos: [],
        failedItems: [],
        message: '',
        isError: false,

        init() {
            this.syncToLivewire();

            window.addEventListener('store-created', async (event) => {
                const storeId = Number(event.detail?.storeId || 0);
                const storeUrl = event.detail?.storeUrl || null;

                if (!storeId) {
                    return;
                }

                this.createdStoreId = storeId;
                this.statusText = 'تم إنشاء المتجر، جاري رفع الوسائط...';

                this.uploadAllPendingForStore(storeId)
                    .then(() => {
                        if (!storeUrl) {
                            this.finishUpload();
                            return;
                        }

                        if (window.Livewire?.navigate) {
                            window.Livewire.navigate(storeUrl);
                        } else {
                            window.location.assign(storeUrl);
                        }

                        setTimeout(() => {
                            if (this.isUploading) {
                                this.finishUpload();
                            }
                        }, 10000);
                    })
                    .catch((error) => {
                        this.fail(error.message || 'تعذر رفع بعض الوسائط بعد إنشاء المتجر.');
                        this.finishUpload();
                    });
            });

            window.addEventListener('beforeunload', (event) => {
                if (!this.isUploading) return;
                event.preventDefault();
                event.returnValue = '';
            });
        },

        selectLogo(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.logoFile = file;
            this.logoName = '';
            this.$wire.set('data.logo', null);
            this.statusText = 'تم تجهيز الشعار للرفع بعد إنشاء المتجر';
        },

        selectImages(event) {
            const files = Array.from(event.target.files || []);
            if (!files.length) return;

            this.pendingImages.push(...files);
            this.statusText = 'تم تجهيز الصور للرفع بعد إنشاء المتجر';
        },

        selectVideos(event) {
            const files = Array.from(event.target.files || []);
            if (!files.length) return;

            this.pendingVideos.push(...files);
            this.statusText = 'تم تجهيز الفيديوهات للرفع بعد إنشاء المتجر';
        },

        async uploadAllPendingForStore(storeId) {
            const uploadQueue = [];
            if (this.logoFile) {
                uploadQueue.push({ kind: 'logo', file: this.logoFile, directory: 'stores/logos' });
            }
            for (const image of this.pendingImages) {
                uploadQueue.push({ kind: 'image', file: image, directory: 'stores/media/images' });
            }
            for (const video of this.pendingVideos) {
                uploadQueue.push({ kind: 'video', file: video, directory: 'stores/media/videos' });
            }

            if (!uploadQueue.length) {
                this.failedItems = [];
                this.clearMessages();
                this.statusText = 'لا توجد ملفات للرفع';
                return;
            }

            this.startUpload();
            this.failedItems = [];
            this.clearMessages();

            const totalItems = uploadQueue.length;
            let completedItems = 0;

            this.emitCounter(totalItems, completedItems);

            const emitOverallProgress = (filePercent = 0) => {
                const percent = Math.min(
                    99,
                    Math.round(((completedItems + (filePercent / 100)) / totalItems) * 100),
                );

                window.dispatchEvent(new CustomEvent('s3-upload-progress', {
                    detail: {
                        progress: Math.max(1, percent),
                        status: this.statusText,
                    },
                }));
            };

            try {
                if (this.logoFile) {
                    this.pushStatus('جاري رفع الشعار...');
                    const result = await this.uploadSingleFile(this.logoFile, 'stores/logos', emitOverallProgress);
                    await this.attachUploadedToStore(storeId, {
                        key: result.key,
                        file_name: this.normalizeFileName(this.logoFile.name),
                        mime_type: this.logoFile.type || null,
                        kind: 'logo',
                    });
                    completedItems++;
                    this.emitCounter(totalItems, completedItems);
                    emitOverallProgress(100);
                    this.pushStatus('تم رفع الشعار بنجاح');
                    this.logoName = this.logoFile.name;
                    this.$wire.set('data.logo', result.key);
                    this.logoFile = null;
                }

                for (const file of this.pendingImages) {
                    try {
                        this.pushStatus(`جاري رفع صورة: ${file.name}`);
                        const result = await this.uploadSingleFile(file, 'stores/media/images', emitOverallProgress);
                        await this.attachUploadedToStore(storeId, {
                            key: result.key,
                            file_name: this.normalizeFileName(file.name),
                            mime_type: file.type || null,
                            kind: 'image',
                        });
                        completedItems++;
                        this.emitCounter(totalItems, completedItems);
                        emitOverallProgress(100);
                        this.pushStatus(`تم رفع صورة: ${file.name}`);
                        this.imageKeys.push(result.key);
                    } catch (error) {
                        completedItems++;
                        this.emitCounter(totalItems, completedItems);
                        emitOverallProgress(100);
                        this.failedItems.push({ type: 'image', name: file.name, file });
                        this.pushStatus(`فشل رفع صورة: ${file.name}`);
                    }
                }

                this.pendingImages = [];

                for (const file of this.pendingVideos) {
                    try {
                        this.pushStatus(`جاري التحقق من فيديو: ${file.name}`);
                        const duration = await this.getVideoDuration(file);
                        if (duration > 60) {
                            throw new Error(`الفيديو ${file.name} مدته ${Math.round(duration)} ثانية وتتجاوز 60 ثانية.`);
                        }

                        this.pushStatus(`جاري رفع فيديو: ${file.name}`);
                        const result = await this.uploadSingleFile(file, 'stores/media/videos', emitOverallProgress);
                        await this.attachUploadedToStore(storeId, {
                            key: result.key,
                            file_name: this.normalizeFileName(file.name),
                            mime_type: file.type || null,
                            kind: 'video',
                        });
                        completedItems++;
                        this.emitCounter(totalItems, completedItems);
                        emitOverallProgress(100);
                        this.pushStatus(`تم رفع فيديو: ${file.name}`);
                        this.videoKeys.push(result.key);
                    } catch (error) {
                        completedItems++;
                        this.emitCounter(totalItems, completedItems);
                        emitOverallProgress(100);
                        this.failedItems.push({ type: 'video', name: file.name, file });
                        this.pushStatus(`فشل رفع فيديو: ${file.name}`);
                    }
                }

                this.pendingVideos = [];
                this.syncToLivewire();

                if (this.failedItems.length) {
                    throw new Error('بعض الملفات فشل رفعها. يرجى إعادة المحاولة.');
                }

                this.ok('تم رفع الوسائط وربطها بالمتجر بنجاح.');
                this.statusText = 'اكتمل رفع كل الوسائط بنجاح';
            }
        },

        async retryFailed() {
            if (!this.failedItems.length) {
                return;
            }

            const retry = [...this.failedItems];
            this.failedItems = [];

            this.startUpload();
            this.clearMessages();

            try {
                const totalItems = retry.length;
                let completedItems = 0;

                this.emitCounter(totalItems, completedItems);

                const emitOverallProgress = (filePercent = 0) => {
                    const percent = Math.min(
                        99,
                        Math.round(((completedItems + (filePercent / 100)) / totalItems) * 100),
                    );

                    window.dispatchEvent(new CustomEvent('s3-upload-progress', {
                        detail: {
                            progress: Math.max(1, percent),
                            status: this.statusText,
                        },
                    }));
                };

                for (const item of retry) {
                    try {
                        if (item.type === 'video') {
                            const duration = await this.getVideoDuration(item.file);
                            if (duration > 60) {
                                throw new Error(`الفيديو ${item.name} يتجاوز 60 ثانية.`);
                            }
                        }

                        const targetDir = item.type === 'video' ? 'stores/media/videos' : 'stores/media/images';
                        const result = await this.uploadSingleFile(item.file, targetDir, emitOverallProgress);

                        await this.attachUploadedToStore(this.createdStoreId, {
                            key: result.key,
                            file_name: this.normalizeFileName(item.file.name),
                            mime_type: item.file.type || null,
                            kind: item.type,
                        });

                        completedItems++;
                        this.emitCounter(totalItems, completedItems);
                        emitOverallProgress(100);

                        if (item.type === 'video') {
                            this.videoKeys.push(result.key);
                        } else {
                            this.imageKeys.push(result.key);
                        }
                    } catch (error) {
                        completedItems++;
                        this.emitCounter(totalItems, completedItems);
                        emitOverallProgress(100);
                        this.failedItems.push(item);
                    }
                }

                this.syncToLivewire();

                if (this.failedItems.length) {
                    this.fail('ما زال هناك ملفات فاشلة. حاول مرة أخرى.');
                    this.statusText = 'تعذر رفع بعض الملفات';
                    return;
                }

                this.ok('تمت إعادة رفع كل الملفات الفاشلة بنجاح.');
                this.statusText = 'اكتمل رفع الملفات الفاشلة بنجاح';
            } catch (e) {
                this.fail(e.message || 'فشل إعادة الرفع');
            } finally {
                this.finishUpload();
            }
        },

        async uploadSingleFile(file, directory, onProgress = null) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const fallbackDirectory = 'stores/logos';
            const candidateDirectories = directory === fallbackDirectory
                ? [directory]
                : [directory, fallbackDirectory];

            let lastError = null;

            for (const currentDirectory of candidateDirectories) {
                try {
                    const signResponse = await fetch('{{ route('s3-direct.sign-put') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            filename: file.name,
                            mime_type: file.type || 'application/octet-stream',
                            directory: currentDirectory,
                        }),
                    });

                    if (!signResponse.ok) {
                        const signError = await this.readJsonMessage(signResponse);
                        throw new Error(signError || 'تعذر تجهيز رابط الرفع إلى S3.');
                    }

                    const signData = await signResponse.json();

                    await this.putWithProgress(signData.upload_url, file, (percent) => {
                        if (typeof onProgress === 'function') {
                            onProgress(percent);
                        }
                    });

                    if (currentDirectory !== directory) {
                        this.pushStatus(`تم استخدام مسار بديل للرفع لملف: ${file.name}`);
                    }

                    return signData;
                } catch (error) {
                    lastError = error;
                }
            }

            throw lastError || new Error('تعذر رفع الملف إلى S3.');
        },

        async putWithProgress(url, file, onProgress) {
            const maxAttempts = 3;

            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                try {
                    await this.sendPut(url, file, onProgress);
                    return;
                } catch (error) {
                    if (attempt === maxAttempts) {
                        throw error;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 700 * attempt));
                }
            }
        },

        sendPut(url, file, onProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', url, true);
                xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const percent = Math.max(1, Math.round((event.loaded / event.total) * 100));
                        onProgress(percent);
                    }
                };

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve();
                    } else {
                        const details = (xhr.responseText || '').slice(0, 180);
                        reject(new Error(`فشل رفع الملف إلى S3 (HTTP ${xhr.status})${details ? ` - ${details}` : ''}.`));
                    }
                };

                xhr.onerror = () => reject(new Error('خطأ شبكة أثناء الرفع إلى S3. غالبًا السبب إعداد CORS في S3 أو ضعف الشبكة.'));
                xhr.send(file);
            });
        },

        async attachUploadedToStore(storeId, payload) {
            if (!storeId) {
                throw new Error('المتجر غير متوفر لربط الوسائط.');
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch('{{ route('s3-direct.attach-uploaded') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    store_id: storeId,
                    ...payload,
                }),
            });

            if (!response.ok) {
                const backendMessage = await this.readJsonMessage(response);
                throw new Error(backendMessage || 'تم رفع الملف لكن فشل ربطه بقاعدة البيانات.');
            }
        },

        async readJsonMessage(response) {
            try {
                const payload = await response.json();
                return payload?.message || payload?.error || null;
            } catch (_error) {
                return null;
            }
        },

        getVideoDuration(file) {
            return new Promise((resolve, reject) => {
                const video = document.createElement('video');
                video.preload = 'metadata';
                video.onloadedmetadata = () => {
                    URL.revokeObjectURL(video.src);
                    resolve(video.duration || 0);
                };
                video.onerror = () => reject(new Error('تعذر قراءة مدة الفيديو.'));
                video.src = URL.createObjectURL(file);
            });
        },

        syncToLivewire() {
            this.$wire.set('data.store_images', [...this.imageKeys]);
            this.$wire.set('data.store_videos', [...this.videoKeys]);
        },

        startUpload() {
            this.isUploading = true;
            document.body.style.overflow = 'hidden';
            window.dispatchEvent(new CustomEvent('s3-upload-start'));
        },

        finishUpload() {
            this.isUploading = false;
            document.body.style.overflow = '';
            window.dispatchEvent(new CustomEvent('s3-upload-finish'));
        },

        ok(text) {
            this.isError = false;
            this.message = text;
        },

        fail(text) {
            this.isError = true;
            this.message = text;
            this.statusText = 'يوجد أخطاء في الرفع';
            window.dispatchEvent(new CustomEvent('s3-upload-error'));
        },

        clearMessages() {
            this.message = '';
            this.isError = false;
        },

        pushStatus(message) {
            window.dispatchEvent(new CustomEvent('s3-upload-status', {
                detail: { message },
            }));
        },

        emitCounter(total, completed) {
            window.dispatchEvent(new CustomEvent('s3-upload-counter', {
                detail: {
                    total,
                    completed,
                },
            }));
        },

        normalizeFileName(name) {
            const fallback = 'file';
            const clean = String(name || fallback).trim() || fallback;
            return clean.length > 240 ? clean.slice(0, 240) : clean;
        },
    }
}
</script>
