# Thông báo thư viện bên thứ ba

ReleaseHub **không có phụ thuộc PHP lúc chạy** (không dùng framework, không dùng thư viện đọc `.env`, không dùng thư viện đồ thị).
Phần tự phát triển: toàn bộ thư mục `app/`, `views/`, `database/`, `bin/`, `tests/`, `public/assets/css/app.css`, `public/assets/js/*.js` (trừ `vendor/`).

| Thành phần | Phiên bản | Giấy phép | Nguồn | Dùng để | Vị trí |
|---|---|---|---|---|---|
| Bootstrap (CSS + JS bundle) | 5.3.3 | MIT | npm `bootstrap@5.3.3` – https://getbootstrap.com | Lưới, form, modal, dropdown | `public/assets/vendor/bootstrap/` |
| Bootstrap Icons | 1.11.3 | MIT | npm `bootstrap-icons@1.11.3` | Biểu tượng | `public/assets/vendor/bootstrap-icons/` |
| Chart.js (UMD) | 4.4.4 | MIT | npm `chart.js@4.4.4` | Biểu đồ dashboard | `public/assets/vendor/chartjs/` |
| Inter (qua @fontsource/inter) | 5.0.18 | SIL OFL 1.1 | npm `@fontsource/inter@5.0.18` (font của Rasmus Andersson) | Phông chữ hỗ trợ tiếng Việt, chạy cục bộ | `public/assets/vendor/inter/` |
| PHPUnit (chỉ khi phát triển) | `^9.6 \|\| ^10.5` (đã chạy thật với 9.6.17) | BSD-3-Clause | https://phpunit.de | Kiểm thử | cài bằng Composer vào `vendor/` (không commit) |
| Playwright cho Python (chỉ khi phát triển) | 1.56 | Apache-2.0 | https://playwright.dev | Kịch bản E2E `tests/e2e/e2e.py` (không bắt buộc) | không đóng gói |

Tệp giấy phép gốc của từng thư viện frontend nằm cạnh tệp thư viện trong `public/assets/vendor/*/`.
Đồ thị phụ thuộc **tự vẽ bằng SVG** và thuật toán phát hiện vòng **tự viết** (`app/Services/DependencyGraphService.php`); không dùng thư viện đồ thị.
