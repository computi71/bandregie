-- The project was renamed from Bandroadie to Bandregie on 2026-07-30.
--
-- The seed files carry the new name, but they never overwrite: an installation
-- that was seeded before the rename keeps the old wording for every language
-- except German, which comes from the source strings. So the stale rows have
-- to go, and this file is numbered 00 for a reason — it runs before the files
-- that insert the new wording, which then fills the gap in the same pass.
--
-- Only rows whose *value* names the old product are touched. Anything a band
-- typed itself stays, unless the band typed the old name, in which case it was
-- about the software and belongs gone too.
SET NAMES utf8mb4;

DELETE FROM translations WHERE value LIKE '%Bandroadie%';
