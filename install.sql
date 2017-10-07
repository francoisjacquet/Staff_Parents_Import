
/**********************************************************************
 install.sql file
 Required if the module adds programs to other modules
***********************************************************************/

/*******************************************************
 profile_id:
 	- 0: user
 	- 1: admin
 	- 2: teacher
 	- 3: parent
 modname: should match the Menu.php entries
 can_use: 'Y'
 can_edit: 'Y' or null (generally null for non admins)
*******************************************************/
--
-- Data for Name: profile_exceptions; Type: TABLE DATA;
--

INSERT INTO profile_exceptions (profile_id, modname, can_use, can_edit)
SELECT 1, 'Staff_Parents_Import/StaffParentsImport.php', 'Y', 'Y'
WHERE NOT EXISTS (SELECT profile_id
	FROM profile_exceptions
	WHERE modname='Staff_Parents_Import/StaffParentsImport.php'
	AND profile_id=1);
