CREATE OR REPLACE VIEW view_montly_bills AS
SELECT
    mb.id,
    mb.id_group,
    mb.name,
    mb.value,
    mb.day,
    bg.name AS group_name
FROM montly_bills mb
LEFT JOIN bills_groups bg ON bg.id = mb.id_group;
