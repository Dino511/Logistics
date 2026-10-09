/* =====================================================================
   Logistics – Shipments schema (SQL Server)

   Run against the LOGISTICS database, e.g.:
     sqlcmd -S .\SQLEXPRESS -E -C -d logistics -i shipments_schema.sql

   Relationships
     customers  1 ─< shipments      (who the shipment is for)
     drivers    1 ─< shipments      (who is delivering it)
     vehicles   1 ─< shipments      (what carries it)
     users      1 ─< shipments      (who created it)
     shipments  1 ─< shipment_items (what is inside it)
     shipments  1 ─< shipment_status_history (every status change)
     shipment_items.inventory_product_id / inventory_location_id
                 ─> Inventory DB products.id / locations.id
                    (a different database, so NO foreign key; the link is
                    by id, and sku / item_name are copied in as a snapshot)

   Prerequisite tables in this database: customers, drivers, vehicles, users.
   Safe to re-run: every object is created only if it does not exist.
   ===================================================================== */
SET NOCOUNT ON;
SET XACT_ABORT ON;
-- Required for the persisted computed column and the filtered indexes below
-- (sqlcmd and some drivers default these to OFF).
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

/* ---------------------------------------------------------------------
   1. shipments – one row per shipment / cargo load
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dbo.shipments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.shipments (
        shipment_id            BIGINT IDENTITY(1,1) NOT NULL,
        tracking_number        NVARCHAR(30)  NOT NULL,          -- public reference, e.g. SH-2026-000123

        customer_id            BIGINT        NULL,
        driver_id              BIGINT        NULL,
        vehicle_id             BIGINT        NULL,
        created_by             BIGINT        NULL,              -- users.id

        -- Origin
        origin_name            NVARCHAR(150) NOT NULL,
        origin_address         NVARCHAR(255) NOT NULL,
        origin_city            NVARCHAR(100) NOT NULL,
        origin_province        NVARCHAR(100) NULL,
        origin_postal_code     NVARCHAR(20)  NULL,
        origin_country         NVARCHAR(100) NOT NULL CONSTRAINT DF_shipments_origin_country DEFAULT N'Philippines',

        -- Destination
        destination_name       NVARCHAR(150) NOT NULL,
        destination_address    NVARCHAR(255) NOT NULL,
        destination_city       NVARCHAR(100) NOT NULL,
        destination_province   NVARCHAR(100) NULL,
        destination_postal_code NVARCHAR(20) NULL,
        destination_country    NVARCHAR(100) NOT NULL CONSTRAINT DF_shipments_dest_country DEFAULT N'Philippines',

        -- Lifecycle. "On time" is an outcome, not a state, so it is derived below.
        status                 NVARCHAR(20)  NOT NULL CONSTRAINT DF_shipments_status DEFAULT N'pending',
        scheduled_pickup_at    DATETIME2(0)  NULL,
        dispatched_at          DATETIME2(0)  NULL,
        scheduled_delivery_at  DATETIME2(0)  NOT NULL,
        actual_delivery_at     DATETIME2(0)  NULL,

        total_weight_kg        DECIMAL(10,2) NULL,
        notes                  NVARCHAR(1000) NULL,

        created_at             DATETIME2(0)  NOT NULL CONSTRAINT DF_shipments_created DEFAULT SYSUTCDATETIME(),
        updated_at             DATETIME2(0)  NOT NULL CONSTRAINT DF_shipments_updated DEFAULT SYSUTCDATETIME(),

        -- Derived, indexable outcome: NULL until delivered, then on_time / late.
        delivery_result AS (
            CASE WHEN actual_delivery_at IS NULL THEN NULL
                 WHEN actual_delivery_at <= scheduled_delivery_at THEN 'on_time'
                 ELSE 'late' END
        ) PERSISTED,

        CONSTRAINT PK_shipments PRIMARY KEY CLUSTERED (shipment_id),
        CONSTRAINT UQ_shipments_tracking_number UNIQUE (tracking_number),
        CONSTRAINT CK_shipments_status CHECK (status IN (N'pending', N'ready_for_pickup', N'picked_up', N'in_transit', N'out_for_delivery', N'delivery_attempted', N'held_for_pickup', N'delayed', N'delivered', N'returned', N'cancelled')),
        -- A delivered shipment must record when it arrived.
        CONSTRAINT CK_shipments_delivered_has_date CHECK (status <> N'delivered' OR actual_delivery_at IS NOT NULL),
        CONSTRAINT CK_shipments_weight CHECK (total_weight_kg IS NULL OR total_weight_kg >= 0),

        CONSTRAINT FK_shipments_customer FOREIGN KEY (customer_id) REFERENCES dbo.customers (id) ON DELETE SET NULL,
        CONSTRAINT FK_shipments_driver   FOREIGN KEY (driver_id)   REFERENCES dbo.drivers (id),
        CONSTRAINT FK_shipments_vehicle  FOREIGN KEY (vehicle_id)  REFERENCES dbo.vehicles (id),
        CONSTRAINT FK_shipments_creator  FOREIGN KEY (created_by)  REFERENCES dbo.users (id)
    );
END
GO

/* ---------------------------------------------------------------------
   2. shipment_items – what is in each shipment (links to Inventory)
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dbo.shipment_items', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.shipment_items (
        shipment_item_id       BIGINT IDENTITY(1,1) NOT NULL,
        shipment_id            BIGINT        NOT NULL,

        -- Pointers into the Inventory database (no FK across databases).
        inventory_product_id   BIGINT        NOT NULL,          -- Inventory products.id
        inventory_location_id  BIGINT        NULL,              -- Inventory locations.id the stock ships from

        -- Snapshot at booking time, so history stays correct if the product is renamed or removed.
        sku                    NVARCHAR(100) NOT NULL,          -- Inventory products.code
        item_name              NVARCHAR(255) NOT NULL,
        quantity               INT           NOT NULL,
        unit_weight_kg         DECIMAL(10,3) NULL,

        created_at             DATETIME2(0)  NOT NULL CONSTRAINT DF_shipment_items_created DEFAULT SYSUTCDATETIME(),

        CONSTRAINT PK_shipment_items PRIMARY KEY CLUSTERED (shipment_item_id),
        CONSTRAINT FK_shipment_items_shipment FOREIGN KEY (shipment_id) REFERENCES dbo.shipments (shipment_id) ON DELETE CASCADE,
        CONSTRAINT CK_shipment_items_quantity CHECK (quantity > 0),
        -- A product appears once per shipment and source location; change the quantity instead of duplicating rows.
        CONSTRAINT UQ_shipment_items_line UNIQUE (shipment_id, inventory_product_id, inventory_location_id)
    );
END
GO

/* ---------------------------------------------------------------------
   3. shipment_status_history – audit trail / tracking timeline
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dbo.shipment_status_history', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.shipment_status_history (
        history_id     BIGINT IDENTITY(1,1) NOT NULL,
        shipment_id    BIGINT        NOT NULL,
        status         NVARCHAR(20)  NOT NULL,
        note           NVARCHAR(500) NULL,                      -- e.g. "Held at Cebu hub, weather"
        changed_by     BIGINT        NULL,                      -- users.id
        changed_at     DATETIME2(0)  NOT NULL CONSTRAINT DF_ssh_changed_at DEFAULT SYSUTCDATETIME(),

        CONSTRAINT PK_shipment_status_history PRIMARY KEY CLUSTERED (history_id),
        CONSTRAINT FK_ssh_shipment FOREIGN KEY (shipment_id) REFERENCES dbo.shipments (shipment_id) ON DELETE CASCADE,
        CONSTRAINT FK_ssh_user     FOREIGN KEY (changed_by)  REFERENCES dbo.users (id),
        CONSTRAINT CK_ssh_status CHECK (status IN (N'pending', N'ready_for_pickup', N'picked_up', N'in_transit', N'out_for_delivery', N'delivery_attempted', N'held_for_pickup', N'delayed', N'delivered', N'returned', N'cancelled'))
    );
END
GO

/* ---------------------------------------------------------------------
   4. Indexes  (tracking_number is already indexed by its UNIQUE constraint;
      the unique line index on shipment_items already covers shipment_id)
   --------------------------------------------------------------------- */

-- Dashboard / list filters: "all delayed shipments", "in transit due soonest".
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipments_status_scheduled' AND object_id = OBJECT_ID(N'dbo.shipments'))
    CREATE NONCLUSTERED INDEX IX_shipments_status_scheduled
        ON dbo.shipments (status, scheduled_delivery_at)
        INCLUDE (tracking_number, destination_city, driver_id);

-- Small, fast index for the shipments that still need attention.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipments_open' AND object_id = OBJECT_ID(N'dbo.shipments'))
    CREATE NONCLUSTERED INDEX IX_shipments_open
        ON dbo.shipments (scheduled_delivery_at)
        INCLUDE (status, tracking_number)
        WHERE status IN (N'pending', N'in_transit', N'delayed');

-- On-time / late reporting over a date range.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipments_delivery_result' AND object_id = OBJECT_ID(N'dbo.shipments'))
    CREATE NONCLUSTERED INDEX IX_shipments_delivery_result
        ON dbo.shipments (delivery_result, actual_delivery_at)
        WHERE actual_delivery_at IS NOT NULL;

-- Foreign keys are not indexed automatically in SQL Server.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipments_customer' AND object_id = OBJECT_ID(N'dbo.shipments'))
    CREATE NONCLUSTERED INDEX IX_shipments_customer ON dbo.shipments (customer_id) WHERE customer_id IS NOT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipments_driver' AND object_id = OBJECT_ID(N'dbo.shipments'))
    CREATE NONCLUSTERED INDEX IX_shipments_driver ON dbo.shipments (driver_id, status) WHERE driver_id IS NOT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipments_vehicle' AND object_id = OBJECT_ID(N'dbo.shipments'))
    CREATE NONCLUSTERED INDEX IX_shipments_vehicle ON dbo.shipments (vehicle_id, status) WHERE vehicle_id IS NOT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipments_created_by' AND object_id = OBJECT_ID(N'dbo.shipments'))
    CREATE NONCLUSTERED INDEX IX_shipments_created_by ON dbo.shipments (created_by) WHERE created_by IS NOT NULL;

-- "Which shipments contain product X?" and "how much of X is committed to shipments?"
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipment_items_product' AND object_id = OBJECT_ID(N'dbo.shipment_items'))
    CREATE NONCLUSTERED INDEX IX_shipment_items_product ON dbo.shipment_items (inventory_product_id) INCLUDE (shipment_id, quantity);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipment_items_sku' AND object_id = OBJECT_ID(N'dbo.shipment_items'))
    CREATE NONCLUSTERED INDEX IX_shipment_items_sku ON dbo.shipment_items (sku);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_shipment_items_location' AND object_id = OBJECT_ID(N'dbo.shipment_items'))
    CREATE NONCLUSTERED INDEX IX_shipment_items_location ON dbo.shipment_items (inventory_location_id) WHERE inventory_location_id IS NOT NULL;

-- Timeline lookup for one shipment, newest first.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ssh_shipment_changed' AND object_id = OBJECT_ID(N'dbo.shipment_status_history'))
    CREATE NONCLUSTERED INDEX IX_ssh_shipment_changed ON dbo.shipment_status_history (shipment_id, changed_at DESC);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ssh_user' AND object_id = OBJECT_ID(N'dbo.shipment_status_history'))
    CREATE NONCLUSTERED INDEX IX_ssh_user ON dbo.shipment_status_history (changed_by) WHERE changed_by IS NOT NULL;
GO

/* ---------------------------------------------------------------------
   5. Optional: live stock next to each shipment line (same SQL Server
      instance; needs SELECT on the inventory database). Uncomment to use.
   ---------------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_shipment_items_live AS
SELECT si.shipment_id, si.sku, si.item_name, si.quantity,
       inv.quantity AS stock_at_source_now
FROM dbo.shipment_items si
LEFT JOIN inventory.dbo.inventories inv
       ON inv.product_id = si.inventory_product_id
      AND inv.location_id = si.inventory_location_id;
   --------------------------------------------------------------------- */
PRINT 'Shipments schema ready.';
GO
