-- The note beside a device tree total is gone: a sum adds up what is there,
-- and saying so in brackets told nobody anything. Its key is unused now.
SET NAMES utf8mb4;

DELETE FROM translations WHERE tkey = 'eq_total_partial';
