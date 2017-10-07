
/**********************************************************
 delete.sql file
 Required if install.sql file present
 - Delete profile exceptions
***********************************************************/

--
-- Delete profile exceptions
--

DELETE FROM profile_exceptions WHERE modname='Staff_Parents_Import/StaffParentsImport.php';

