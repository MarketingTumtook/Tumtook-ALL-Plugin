(function () {
    'use strict';
    function init() {
        document.querySelectorAll('.ttgc-settings-test').forEach(function (root) {
            var button = root.querySelector('[data-ttgc-test-api]');
            var status = root.querySelector('[data-ttgc-api-status]');
            var details = root.querySelector('[data-ttgc-api-details]');
            var codes = root.querySelector('[data-ttgc-api-codes]');
            var images = root.querySelector('[data-ttgc-api-images]');
            button.addEventListener('click', function () {
                if (button.disabled) return;
                var url = root.querySelector('#ttgc-api-url').value.trim();
                if (!url) { status.textContent = 'กรุณากรอก API URL ก่อนทดสอบ'; return; }
                button.disabled = true;
                details.textContent = ''; codes.textContent = ''; images.textContent = '';
                status.textContent = 'กำลังทดสอบ URL และอ่านข้อมูล Gallery…';
                var body = new URLSearchParams({ action: 'ttgc_test_api', nonce: root.dataset.nonce, api_url: url, code: root.querySelector('#ttgc-test-code').value.trim() });
                var controller = new AbortController();
                var timeout = window.setTimeout(function () { controller.abort(); }, 12000);
                fetch(root.dataset.url, { method: 'POST', credentials: 'same-origin', body: body, signal: controller.signal })
                    .then(function (response) { return response.json(); })
                    .then(function (response) {
                        if (!response.success) throw new Error(response.data && response.data.message || 'ทดสอบไม่สำเร็จ');
                        var data = response.data;
                        status.textContent = data.requested_code
                            ? !data.matched_records ? 'ติดต่อ API ได้ แต่ไม่พบ code ที่ระบุ' : data.display_image_count ? 'พบ code และรูปพร้อมแสดง' : 'พบ code แต่ไม่มีรูปที่แสดงได้'
                            : data.code_count ? 'ติดต่อ API ได้และพบข้อมูล Gallery' : 'ติดต่อ API ได้ แต่ยังไม่พบรายการ Gallery ที่มี code';
                        var rows = [['HTTP', data.http_status], ['รายการใน items', data.record_count], ['จำนวน code', data.code_count], ['จำนวน URL รูปทั้งหมด', data.image_count]];
                        if (data.requested_code) rows.push(['code ที่ทดสอบ', data.requested_code], ['รูปไม่ซ้ำพร้อมแสดง (สูงสุด 50)', data.display_image_count]);
                        rows.forEach(function (row) {
                            var title = document.createElement('dt'), value = document.createElement('dd');
                            title.textContent = row[0]; title.style.fontWeight = '600'; value.textContent = String(row[1]);
                            details.appendChild(title); details.appendChild(value);
                        });
                        var table = document.createElement('table'); table.className = 'widefat striped';
                        var head = document.createElement('tr');
                        ['Code', 'รายการ', 'URL รูป'].forEach(function (text) { var cell = document.createElement('th'); cell.textContent = text; head.appendChild(cell); });
                        table.appendChild(head);
                        data.codes.forEach(function (summary) {
                            var row = document.createElement('tr');
                            [summary.code, summary.records, summary.images].forEach(function (text) { var cell = document.createElement('td'); cell.textContent = String(text); row.appendChild(cell); });
                            table.appendChild(row);
                        });
                        codes.appendChild(table);
                        var note = document.createElement('p');
                        note.textContent = 'ผลทดสอบนี้ยังไม่บันทึกค่า URL หากถูกต้องให้กดบันทึกการเปลี่ยนแปลง' + (data.code_count > 100 ? ' ตารางแสดง 100 code แรก สามารถระบุ code เพื่อทดสอบชุดที่ต้องการได้' : '');
                        codes.appendChild(note);
                        data.preview.forEach(function (item) {
                            var image = document.createElement('img'); image.src = item.image; image.alt = item.alt || 'ตัวอย่างจาก API';
                            image.style.width = '100%'; image.style.height = 'auto'; image.style.borderRadius = '8px';
                            image.addEventListener('error', function () { image.hidden = true; var text = document.createElement('span'); text.textContent = 'พบ URL แต่ไฟล์รูปเปิดไม่ได้'; images.appendChild(text); });
                            images.appendChild(image);
                        });
                    }).catch(function (error) { status.textContent = error.name === 'AbortError' ? 'ทดสอบเกินเวลา กรุณาลองอีกครั้ง' : error.message; })
                    .finally(function () { window.clearTimeout(timeout); button.disabled = false; });
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
