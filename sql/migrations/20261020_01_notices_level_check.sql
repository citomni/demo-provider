-- Apply once to an existing database whose demoprov_notices table predates this change. The
-- level check then compares bytes, so a level with a trailing space, such as 'info ', is refused
-- like any other value than 'info' and 'warning'. The statement fails while a stored row breaks
-- the new check, and it changes no value. Such rows show with this query:
--   SELECT id, level FROM demoprov_notices WHERE CAST(level AS BINARY) NOT IN ('info', 'warning')
-- DDL implicitly commits. The order relative to deploying the code does not matter. MySQL 8.0.16
-- to 8.0.18 take DROP CHECK instead of DROP CONSTRAINT. A fresh install imports
-- sql/citomni_demo-provider.sql instead.
ALTER TABLE demoprov_notices
	DROP CONSTRAINT chk_demoprov_notices_level,
	ADD CONSTRAINT chk_demoprov_notices_level CHECK (CAST(level AS BINARY) IN ('info', 'warning'));
