-- ReleaseHub 002: trigger bảo vệ dữ liệu đã chốt (snapshot bất biến ở tầng CSDL)
CREATE TRIGGER trg_snapshot_bi BEFORE INSERT ON release_snapshot_items FOR EACH ROW
BEGIN
    IF (SELECT status FROM releases WHERE id = NEW.release_id) <> 'planned' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Snapshot cua phien ban da chot khong the them moi';
    END IF;
END
-- ;;
CREATE TRIGGER trg_snapshot_bu BEFORE UPDATE ON release_snapshot_items FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Snapshot phat hanh la bat bien (khong cho sua)';
END
-- ;;
CREATE TRIGGER trg_snapshot_bd BEFORE DELETE ON release_snapshot_items FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Snapshot phat hanh la bat bien (khong cho xoa)';
END
-- ;;
CREATE TRIGGER trg_release_bu BEFORE UPDATE ON releases FOR EACH ROW
BEGIN
    IF OLD.status = 'released' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Phien ban da chot khong the sua';
    END IF;
END
-- ;;
CREATE TRIGGER trg_release_bd BEFORE DELETE ON releases FOR EACH ROW
BEGIN
    IF OLD.status = 'released' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Phien ban da chot khong the xoa';
    END IF;
END
-- ;;
CREATE TRIGGER trg_item_bi BEFORE INSERT ON release_items FOR EACH ROW
BEGIN
    IF (SELECT status FROM releases WHERE id = NEW.release_id) = 'released' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Phien ban da chot khong the them cong viec';
    END IF;
END
-- ;;
CREATE TRIGGER trg_item_bu BEFORE UPDATE ON release_items FOR EACH ROW
BEGIN
    IF (SELECT status FROM releases WHERE id = OLD.release_id) = 'released' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Phien ban da chot khong the sua danh sach';
    END IF;
END
-- ;;
CREATE TRIGGER trg_item_bd BEFORE DELETE ON release_items FOR EACH ROW
BEGIN
    IF (SELECT status FROM releases WHERE id = OLD.release_id) = 'released' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Phien ban da chot khong the go cong viec';
    END IF;
END
