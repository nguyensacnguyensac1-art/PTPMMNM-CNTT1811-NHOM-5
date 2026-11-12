"""E2E bằng Playwright (Chromium). Chạy: python3 tests/e2e/e2e.py  (cần app chạy ở BASE và DB vừa seed)."""
import os, re, subprocess, sys
from playwright.sync_api import sync_playwright

BASE = os.environ.get("BASE", "http://127.0.0.1:8080")
PW = "ReleaseHub@2026"
results = []

def check(name, ok, detail=""):
    results.append((name, bool(ok), detail))
    print(("PASS " if ok else "FAIL ") + name + (f"  [{detail}]" if detail and not ok else ""))

def db(sql):
    return subprocess.run(["mysql", "-uroot", "-N", "-B", "releasehub", "-e", sql], capture_output=True, text=True).stdout.strip()

def login(ctx, email):
    pg = ctx.new_page()
    errors = []
    pg.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
    pg.on("pageerror", lambda e: errors.append(str(e)))
    pg.goto(BASE + "/login")
    pg.fill("#email", email); pg.fill("#password", PW)
    pg.click("button[type=submit]")
    pg.wait_for_load_state("networkidle")
    return pg, errors

with sync_playwright() as p:
    br = p.chromium.launch()

    # 1. Dashboard + biểu đồ + không lỗi console
    ctx = br.new_context(viewport={"width": 1366, "height": 768})
    pg, errs = login(ctx, "minh.pm@releasehub.local")
    check("đăng nhập chuyển tới dashboard", pg.url.rstrip("/") == BASE.rstrip("/"), pg.url)
    pg.wait_for_selector("#chStatus")
    pg.wait_for_timeout(600)
    drawn = pg.evaluate("[...document.querySelectorAll('canvas')].every(c => c.width > 0 && c.height > 0)")
    check("dashboard vẽ 3 biểu đồ Chart.js", drawn and pg.locator("canvas").count() == 3)
    check("dashboard không lỗi console", not errs, str(errs))
    # font Inter & vendor chạy cục bộ (không gọi ngoài)
    ext = []
    pg.on("request", lambda r: ext.append(r.url) if not r.url.startswith(BASE) else None)
    pg.goto(BASE + "/projects/1/issues"); pg.wait_for_load_state("networkidle")
    check("không có request tới host bên ngoài (chạy offline)", not ext, str(ext[:3]))

    # 2. Responsive: không tràn ngang toàn trang
    for w, h, label in [(1366, 768, "1366x768"), (1920, 1080, "1920x1080"), (390, 844, "mobile 390")]:
        c2 = br.new_context(viewport={"width": w, "height": h})
        q, _ = login(c2, "minh.pm@releasehub.local")
        bad = []
        for u in ["/", "/projects", "/issues", "/projects/1", "/projects/1/issues", "/projects/1/board", "/projects/1/graph", "/projects/1/releases", "/projects/1/members", "/issues/1", "/releases/2", "/releases/1"]:
            q.goto(BASE + u); q.wait_for_load_state("networkidle")
            sw = q.evaluate("document.documentElement.scrollWidth"); iw = q.evaluate("window.innerWidth")
            if sw > iw + 1: bad.append(f"{u}:{sw}>{iw}")
        check(f"responsive {label}: không tràn ngang", not bad, ", ".join(bad))
        c2.close()

    # 3. Kanban: đổi trạng thái qua backend, thất bại thì hoàn nguyên
    pg.goto(BASE + "/projects/1/board"); pg.wait_for_load_state("networkidle")
    card = pg.locator('.kb-col[data-status="open"] .kb-card').first
    cid = card.get_attribute("data-id"); code = card.locator(".code-link").inner_text()
    pg.drag_and_drop(f'.kb-card[data-id="{cid}"]', '.kb-col[data-status="resolved"]')
    pg.wait_for_timeout(900)
    in_open = pg.locator(f'.kb-col[data-status="open"] .kb-card[data-id="{cid}"]').count() == 1
    toast = pg.locator(".rh-toast").all_inner_texts()
    check("Kanban: kéo Open→Resolved bị backend từ chối, thẻ hoàn nguyên", in_open and db(f"select status from issues where id={cid}") == "open", str(toast))
    check("Kanban: có thông báo lý do", any("chuyển" in t.lower() or "không" in t.lower() for t in toast), str(toast))
    pg.drag_and_drop(f'.kb-card[data-id="{cid}"]', '.kb-col[data-status="in_progress"]')
    pg.wait_for_timeout(900)
    check("Kanban: kéo Open→In Progress thành công và lưu DB", pg.locator(f'.kb-col[data-status="in_progress"] .kb-card[data-id="{cid}"]').count() == 1 and db(f"select status from issues where id={cid}") == "in_progress")
    pg.reload(); pg.wait_for_load_state("networkidle")
    check("Kanban: tải lại trang vẫn giữ trạng thái (dữ liệu thật)", pg.locator(f'.kb-col[data-status="in_progress"] .kb-card[data-id="{cid}"]').count() == 1)
    # menu thay thế (không kéo thả)
    pg.locator(f'.kb-card[data-id="{cid}"] button[data-bs-toggle=dropdown]').click()
    pg.locator(f'.kb-card[data-id="{cid}"] [data-move="open"]').click()
    pg.wait_for_timeout(800)
    check("Kanban: menu thay thế đổi trạng thái được", db(f"select status from issues where id={cid}") == "open")

    # 4. Xung đột chỉnh sửa giữa hai phiên
    ca = br.new_context(); cb = br.new_context()
    pa, _ = login(ca, "huy.dev@releasehub.local"); pb, _ = login(cb, "lan.dev@releasehub.local")
    iid = 12
    orig = db(f"select title from issues where id={iid}")
    pa.goto(f"{BASE}/issues/{iid}"); pb.goto(f"{BASE}/issues/{iid}")
    for pgx in (pa, pb):
        pgx.click("button[data-bs-target=\"#editModal\"]"); pgx.wait_for_selector("#e_title", state="visible")
    pa.fill("#e_title", "Tiêu đề do Huy sửa trước"); pa.click("#edit-form button[type=submit]"); pa.wait_for_load_state("networkidle")
    check("Phiên A lưu thành công", db(f"select title from issues where id={iid}") == "Tiêu đề do Huy sửa trước")
    pb.fill("#e_title", "Tiêu đề do Lan sửa sau"); pb.fill("#e_desc", "Bản nháp dài của Lan cần giữ lại")
    pb.click("#edit-form button[type=submit]")
    pb.wait_for_selector("#conflictModal.show")
    check("Phiên B (lưu sau) thấy màn hình xung đột", "Xung đột" in pb.inner_text("#conflictT"))
    diff = pb.inner_text("#conflict-diff")
    check("Xung đột hiển thị bản của Lan và bản mới nhất của Huy", "Lan sửa sau" in diff and "Huy sửa trước" in diff, diff[:200])
    check("DB không bị ghi đè bởi lượt lưu sau", db(f"select title from issues where id={iid}") == "Tiêu đề do Huy sửa trước")
    pb.click("#conflict-keep"); pb.wait_for_timeout(500)
    check("Giữ bản nháp trong form sau khi xung đột", pb.input_value("#e_title") == "Tiêu đề do Lan sửa sau" and "Bản nháp dài" in pb.input_value("#e_desc"))
    ver_db = db(f"select version from issues where id={iid}")
    check("expected_version được cập nhật nhưng chưa tự lưu", pb.input_value("#edit-form [name=expected_version]") == ver_db and db(f"select title from issues where id={iid}") == "Tiêu đề do Huy sửa trước")
    pb.click("#edit-form button[type=submit]"); pb.wait_for_load_state("networkidle")
    check("Sau khi xem lại, người dùng chủ động lưu thì ghi được", db(f"select title from issues where id={iid}") == "Tiêu đề do Lan sửa sau")
    ca.close(); cb.close()

    # 5. Thêm cạnh gây vòng trên màn hình đồ thị
    pg.goto(BASE + "/projects/1/graph"); pg.wait_for_load_state("networkidle")
    check("Đồ thị phụ thuộc có vẽ SVG", pg.locator("#graph svg").count() == 1 and pg.locator("#graph .g-node").count() > 5)
    pg.select_option("#issue_id", label=re.compile("x").pattern and "VOCAB-3 – " + db("select substr(title,1,50) from issues where id=3")[:50]) if False else None
    pg.select_option("#issue_id", value="3"); pg.select_option("#depends_on_id", value="5")
    pg.click("#dep-form button[type=submit]"); pg.wait_for_selector("#cycle-box:not(.d-none)")
    txt = pg.inner_text("#cycle-box .cycle-chain")
    check("Hiện đúng chuỗi gây vòng trên giao diện", txt == "VOCAB-3 → VOCAB-5 → VOCAB-3", txt)
    # highlight khi nhấn nút
    pg.locator("#graph .g-node").first.click()
    check("Nhấn nút làm nổi bật chuỗi liên quan", pg.locator("#graph .g-node.hl").count() >= 1)

    # 6. Phát hành: bản sẵn sàng được chốt; bản bị chặn thì nút bị khóa
    cr = br.new_context(viewport={"width": 1366, "height": 768}); pr, _ = login(cr, "binh.rm@releasehub.local")
    ch = br.new_context(viewport={"width": 1366, "height": 768}); ph, _ = login(ch, "ha.rm@releasehub.local")
    ph.goto(BASE + "/releases/5"); ph.wait_for_load_state("networkidle")   # PAY v1.1.0: bị Blocker chặn
    check("Bản bị Blocker chặn: nút Chốt bị khóa", ph.locator("button:has-text('Chốt phát hành')").is_disabled())
    check("Hiển thị nguyên nhân chặn Blocker", "cần kiểm thử lại" in ph.inner_text("main"), ph.inner_text(".cond.bad")[:200] if ph.locator(".cond.bad").count() else "")
    ch.close()
    pr.goto(BASE + "/releases/8"); pr.wait_for_load_state("networkidle")   # IOT v1.1.0: phụ thuộc ngoài phiên bản chưa xong
    check("Bản bị phụ thuộc chặn: nút Chốt bị khóa và nêu lý do", pr.locator("button:has-text('Chốt phát hành')").is_disabled() and "ngoài phiên bản" in pr.inner_text("main"))
    pr.goto(BASE + "/releases/2"); pr.wait_for_load_state("networkidle")   # VOCAB v1.1.0: sẵn sàng
    check("Bản sẵn sàng: nút Chốt khả dụng", pr.locator("button:has-text('Chốt phát hành')").is_enabled())
    pr.click("button:has-text('Chốt phát hành')"); pr.wait_for_selector(".modal.show")
    pr.click(".modal.show .btn-primary"); pr.wait_for_load_state("networkidle"); pr.wait_for_timeout(800)
    check("Chốt thành công và chuyển sang trạng thái Đã phát hành", db("select status from releases where id=2") == "released" and "snapshot bất biến" in pr.inner_text("main"))
    check("Snapshot được tạo đúng số mục", db("select count(*) from release_snapshot_items where release_id=2") == "6")
    # sửa công việc nguồn sau khi chốt không đổi snapshot
    before = db("select title from release_snapshot_items where release_id=2 order by issue_id limit 1")
    db("update issues set title='ĐÃ SỬA SAU PHÁT HÀNH' where id in (select issue_id from release_snapshot_items where release_id=2)")
    pr.reload(); pr.wait_for_load_state("networkidle")
    check("Trang phát hành vẫn hiển thị snapshot cũ", before in pr.inner_text("main") and "ĐÃ SỬA SAU" not in pr.inner_text("main"))
    with pr.expect_download() as d:
        pr.click("text=Release notes (.md)")
    content = open(d.value.path(), encoding="utf-8").read()
    check("Tải release notes Markdown từ snapshot", content.startswith("# ") and "## Sửa lỗi" in content and "ĐÃ SỬA SAU" not in content)
    cr.close()

    # 7. Trang 403 thân thiện cho thành viên chỉ thuộc dự án khác
    cu = br.new_context(); pu, _ = login(cu, "user.a@releasehub.local")
    r = pu.goto(BASE + "/projects/2")
    check("Thành viên chỉ thuộc A vào dự án B: 403 có thông báo", r.status == 403 and "không thuộc dự án" in pu.inner_text("main"))
    pu.goto(BASE + "/projects"); pu.wait_for_load_state("networkidle")
    check("Danh sách dự án của user.a chỉ có 1 dự án", pu.locator(".project-card").count() == 1)
    cu.close()

    # 8. Tester ghi kiểm thử Passed cho bản sửa hiện tại (VOCAB-2 sẽ được đưa về Resolved)
    br.close()

bad = [r for r in results if not r[1]]
print(f"\n{len(results) - len(bad)}/{len(results)} PASS")
sys.exit(1 if bad else 0)
