(function () {
    'use strict';
    function init() {
        document.querySelectorAll('.ttgc-diagnostics').forEach(function (root) {
            var button = root.querySelector('[data-ttgc-check]');
            var status = root.querySelector('[data-ttgc-status]');
            var details = root.querySelector('[data-ttgc-details]');
            var images = root.querySelector('[data-ttgc-images]');
            button.addEventListener('click', function () {
                if (button.disabled) return;
                button.disabled = true;
                details.textContent = '';
                images.textContent = '';
                status.textContent = 'กำลังตรวจ code และข้อมูลรูปจาก API…';
                var body = new URLSearchParams({ action: 'ttgc_diagnostics', post_id: root.dataset.postId, nonce: root.dataset.nonce, fresh: root.querySelector('[data-ttgc-fresh]').checked ? '1' : '0' });
                var controller = new AbortController();
                var timeout = window.setTimeout(function () { controller.abort(); }, 12000);
                fetch(root.dataset.url, { method: 'POST', credentials: 'same-origin', body: body, signal: controller.signal })
                    .then(function (response) { return response.json(); })
                    .then(function (response) {
                        if (!response.success) throw new Error(response.data && response.data.message || 'ตรวจข้อมูลไม่สำเร็จ');
                        var data = response.data;
                        status.textContent = !data.matched_records ? 'ไม่พบ code นี้ในข้อมูล API' : data.display_image_count ? 'พบ code ตรงกับ API และมีรูปพร้อมแสดง' : 'พบ code ใน API แต่ไม่มี URL รูปที่ใช้แสดงได้';
                        var rows = [
                            ['โพสต์ ID', data.post_id], ['code ที่ใช้ตรวจ', data.code], ['HTTP จาก API', data.http_status],
                            ['แหล่งข้อมูล', data.source], ['รายการที่ code ตรงกัน', data.matched_records],
                            ['จำนวน URL รูปใน API ของ code นี้', data.api_image_count], ['รูปไม่ซ้ำพร้อมแสดง (สูงสุด 50)', data.display_image_count]
                        ];
                        rows.forEach(function (row) {
                            var title = document.createElement('dt');
                            var value = document.createElement('dd');
                            title.textContent = row[0];
                            title.style.fontWeight = '600';
                            value.textContent = String(row[1]);
                            details.appendChild(title); details.appendChild(value);
                        });
                        data.preview.forEach(function (item) {
                            var image = document.createElement('img');
                            image.src = item.image;
                            image.alt = item.alt || data.code;
                            image.style.width = '100%'; image.style.height = 'auto'; image.style.borderRadius = '8px';
                            image.addEventListener('error', function () {
                                image.hidden = true;
                                var note = document.createElement('span'); note.textContent = 'พบ URL แต่ไฟล์รูปเปิดไม่ได้'; images.appendChild(note);
                            });
                            images.appendChild(image);
                        });
                    }).catch(function (error) { status.textContent = error.name === 'AbortError' ? 'ตรวจข้อมูลเกินเวลา กรุณาลองตรวจอีกครั้ง' : error.message; })
                    .finally(function () { window.clearTimeout(timeout); button.disabled = false; });
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
