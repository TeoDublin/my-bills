CREATE OR REPLACE VIEW view_bills AS
SELECT b.*, bg.name AS group_name
FROM bills b
LEFT JOIN bills_groups bg ON bg.id = b.id_group;
