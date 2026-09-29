/* =====================================================================
   Read-only access for Logistics to the Inventory database (inventory_DB)

   Logistics only ever reads Inventory. This gives the SQL login
   `inv_reader` permission to read every table in inventory_DB and denies
   every kind of change, so Inventory data is protected at the database
   level as well as in the Logistics code.

   Run once as a SQL Server administrator, e.g.:
     sqlcmd -S .\SQLEXPRESS -E -C -i inventory_readonly_login.sql

   Safe to re-run.
   ===================================================================== */
SET NOCOUNT ON;

/* 1. The login. It already exists on this server; this only creates it if
      it doesn't. If you don't know its password, set a new one in step 1b. */
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'inv_reader')
BEGIN
    -- Replace CHANGE_ME with a strong password before running.
    CREATE LOGIN inv_reader WITH PASSWORD = N'CHANGE_ME', CHECK_POLICY = ON, DEFAULT_DATABASE = inventory_DB;
END
GO

/* 1b. Optional: set a new password for the existing login. Remove the two
       dashes, replace CHANGE_ME, then run.                                   */
-- ALTER LOGIN inv_reader WITH PASSWORD = N'CHANGE_ME';
GO

ALTER LOGIN inv_reader WITH DEFAULT_DATABASE = inventory_DB;
GO

USE inventory_DB;
GO

/* 2. A user for the login in inventory_DB, allowed to read every table. */
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'inv_reader')
    CREATE USER inv_reader FOR LOGIN inv_reader;
GO
ALTER ROLE db_datareader ADD MEMBER inv_reader;
GO

/* 3. Explicitly deny every change, so even a later role grant can't allow one. */
DENY INSERT, UPDATE, DELETE, EXECUTE, ALTER, REFERENCES, CONTROL, TAKE OWNERSHIP TO inv_reader;
GO

/* 4. Check: should list db_datareader and the DENY rows. */
SELECT 'role' AS kind, r.name AS detail
FROM sys.database_role_members rm
JOIN sys.database_principals r ON r.principal_id = rm.role_principal_id
WHERE rm.member_principal_id = USER_ID(N'inv_reader')
UNION ALL
SELECT state_desc, permission_name
FROM sys.database_permissions
WHERE grantee_principal_id = USER_ID(N'inv_reader');
GO
