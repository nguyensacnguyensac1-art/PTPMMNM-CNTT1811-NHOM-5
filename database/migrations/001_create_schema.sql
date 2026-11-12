-- ReleaseHub 001: cấu trúc bảng (InnoDB, utf8mb4). Các câu lệnh cách nhau bằng dòng "-- ;;"
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(190) NOT NULL,
    full_name     VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin      TINYINT(1) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE roles (
    code        VARCHAR(32) NOT NULL,
    name        VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE projects (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(16) NOT NULL,
    name          VARCHAR(150) NOT NULL,
    description   TEXT NULL,
    color         CHAR(7) NOT NULL DEFAULT '#4f46e5',
    icon          VARCHAR(40) NOT NULL DEFAULT 'bi-kanban',
    owner_id      INT UNSIGNED NULL,
    issue_counter INT UNSIGNED NOT NULL DEFAULT 0,
    version       INT UNSIGNED NOT NULL DEFAULT 1,
    archived_at   DATETIME NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_code (code),
    KEY idx_projects_archived (archived_at),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE project_members (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    role_code  VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_member (project_id, user_id),
    KEY idx_member_user (user_id),
    CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_pm_role FOREIGN KEY (role_code) REFERENCES roles (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE issues (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id         INT UNSIGNED NOT NULL,
    number             INT UNSIGNED NOT NULL,
    code               VARCHAR(32) NOT NULL,
    type               ENUM('bug','task','improvement') NOT NULL,
    title              VARCHAR(200) NOT NULL,
    description        TEXT NULL,
    priority           ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    severity           ENUM('trivial','minor','major','critical','blocker') NULL,
    status             ENUM('open','in_progress','resolved','verified','closed','reopened') NOT NULL DEFAULT 'open',
    reporter_id        INT UNSIGNED NOT NULL,
    assignee_id        INT UNSIGNED NULL,
    due_date           DATE NULL,
    environment        VARCHAR(255) NULL,
    steps_to_reproduce TEXT NULL,
    expected_result    TEXT NULL,
    actual_result      TEXT NULL,
    fix_ref            VARCHAR(500) NULL,
    fix_revision       INT UNSIGNED NOT NULL DEFAULT 0,
    commit_url         VARCHAR(500) NULL,
    pr_url             VARCHAR(500) NULL,
    version            INT UNSIGNED NOT NULL DEFAULT 1,
    created_at         DATETIME NOT NULL,
    updated_at         DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_issue_number (project_id, number),
    UNIQUE KEY uq_issue_code (project_id, code),
    UNIQUE KEY uq_issue_project (id, project_id),
    KEY idx_issue_status (project_id, status),
    KEY idx_issue_assignee (project_id, assignee_id),
    KEY idx_issue_severity (project_id, severity),
    KEY idx_issue_type (project_id, type),
    KEY idx_issue_updated (project_id, updated_at),
    CONSTRAINT fk_issue_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE RESTRICT,
    CONSTRAINT fk_issue_reporter FOREIGN KEY (reporter_id) REFERENCES users (id),
    CONSTRAINT fk_issue_assignee FOREIGN KEY (assignee_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE dependencies (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id    INT UNSIGNED NOT NULL,
    issue_id      INT UNSIGNED NOT NULL COMMENT 'A: công việc phụ thuộc',
    depends_on_id INT UNSIGNED NOT NULL COMMENT 'B: phải hoàn thành trước A',
    created_by    INT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dependency (issue_id, depends_on_id),
    KEY idx_dep_reverse (depends_on_id, issue_id),
    KEY idx_dep_project (project_id),
    CONSTRAINT fk_dep_issue FOREIGN KEY (issue_id, project_id) REFERENCES issues (id, project_id) ON DELETE RESTRICT,
    CONSTRAINT fk_dep_on FOREIGN KEY (depends_on_id, project_id) REFERENCES issues (id, project_id) ON DELETE RESTRICT,
    CONSTRAINT fk_dep_user FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT chk_dep_not_self CHECK (issue_id <> depends_on_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE comments (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    issue_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_comment_issue (issue_id, id),
    CONSTRAINT fk_comment_issue FOREIGN KEY (issue_id, project_id) REFERENCES issues (id, project_id) ON DELETE RESTRICT,
    CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE test_results (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   INT UNSIGNED NOT NULL,
    issue_id     INT UNSIGNED NOT NULL,
    tester_id    INT UNSIGNED NOT NULL,
    result       ENUM('passed','failed') NOT NULL,
    fix_revision INT UNSIGNED NOT NULL COMMENT 'Bản sửa được kiểm tra (issues.fix_revision tại thời điểm test)',
    fix_ref      VARCHAR(500) NULL COMMENT 'Mã commit/PR của bản sửa được kiểm tra',
    note         TEXT NULL,
    tested_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_test_issue (issue_id, id),
    CONSTRAINT fk_test_issue FOREIGN KEY (issue_id, project_id) REFERENCES issues (id, project_id) ON DELETE RESTRICT,
    CONSTRAINT fk_test_user FOREIGN KEY (tester_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE releases (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id        INT UNSIGNED NOT NULL,
    name              VARCHAR(40) NOT NULL COMMENT 'Mã phiên bản, ví dụ v1.2.0',
    description       TEXT NULL,
    planned_date      DATE NULL,
    owner_id          INT UNSIGNED NULL,
    status            ENUM('planned','released') NOT NULL DEFAULT 'planned',
    released_at       DATETIME NULL,
    released_by       INT UNSIGNED NULL,
    snapshot_checksum CHAR(64) NULL,
    readiness_report  LONGTEXT NULL,
    version           INT UNSIGNED NOT NULL DEFAULT 1,
    created_by        INT UNSIGNED NOT NULL,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_release_name (project_id, name),
    UNIQUE KEY uq_release_project (id, project_id),
    KEY idx_release_status (project_id, status),
    CONSTRAINT fk_release_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE RESTRICT,
    CONSTRAINT fk_release_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_release_by FOREIGN KEY (released_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_release_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE release_items (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  INT UNSIGNED NOT NULL,
    release_id  INT UNSIGNED NOT NULL,
    issue_id    INT UNSIGNED NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    added_by    INT UNSIGNED NOT NULL,
    added_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_release_item (release_id, issue_id),
    KEY idx_item_issue (issue_id),
    CONSTRAINT fk_item_release FOREIGN KEY (release_id, project_id) REFERENCES releases (id, project_id) ON DELETE RESTRICT,
    CONSTRAINT fk_item_issue FOREIGN KEY (issue_id, project_id) REFERENCES issues (id, project_id) ON DELETE RESTRICT,
    CONSTRAINT fk_item_user FOREIGN KEY (added_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE release_snapshot_items (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id       INT UNSIGNED NOT NULL,
    project_id       INT UNSIGNED NOT NULL,
    issue_id         INT UNSIGNED NOT NULL COMMENT 'Chỉ để tham chiếu; nội dung hiển thị luôn lấy từ các cột sao chép bên dưới',
    issue_code       VARCHAR(32) NOT NULL,
    title            VARCHAR(200) NOT NULL,
    type             VARCHAR(20) NOT NULL,
    category         VARCHAR(30) NOT NULL,
    change_summary   TEXT NOT NULL,
    priority         VARCHAR(20) NOT NULL,
    severity         VARCHAR(20) NULL,
    status           VARCHAR(20) NOT NULL,
    is_required      TINYINT(1) NOT NULL DEFAULT 1,
    assignee_name    VARCHAR(120) NULL,
    fix_ref          VARCHAR(500) NULL,
    fix_revision     INT UNSIGNED NOT NULL DEFAULT 0,
    dependency_codes TEXT NULL,
    test_evidence    LONGTEXT NULL,
    finalized_by     VARCHAR(120) NOT NULL,
    finalized_at     DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_snapshot_item (release_id, issue_id),
    KEY idx_snapshot_release (release_id),
    CONSTRAINT fk_snapshot_release FOREIGN KEY (release_id, project_id) REFERENCES releases (id, project_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE issue_histories (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    issue_id   INT UNSIGNED NOT NULL,
    actor_id   INT UNSIGNED NULL,
    action     VARCHAR(40) NOT NULL,
    changes    TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_hist_issue (issue_id, id),
    KEY idx_hist_project (project_id, id),
    CONSTRAINT fk_hist_issue FOREIGN KEY (issue_id, project_id) REFERENCES issues (id, project_id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  INT UNSIGNED NULL,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id   INT UNSIGNED NULL,
    ip_address  VARCHAR(45) NULL,
    details     TEXT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_project (project_id, id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- ;;
INSERT INTO roles (code, name, description, sort_order) VALUES
 ('project_manager', 'Project Manager', 'Quản lý dự án, thành viên và công việc', 1),
 ('release_manager', 'Release Manager', 'Lập kế hoạch và chốt phát hành', 2),
 ('developer', 'Developer', 'Xử lý công việc, ghi nhận bản sửa', 3),
 ('tester', 'Tester', 'Kiểm thử lại và xác nhận kết quả', 4),
 ('viewer', 'Viewer', 'Chỉ xem', 5)
