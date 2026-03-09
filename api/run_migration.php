<?php
require_once 'config.php';
require_once 'db_connect.php';

$sql = "
IF NOT EXISTS (
  SELECT * FROM sys.columns 
  WHERE object_id = OBJECT_ID(N'[dbo].[ChatMessages]') 
  AND name = 'Attachments'
)
BEGIN
    ALTER TABLE [dbo].[ChatMessages] ADD Attachments NVARCHAR(MAX) NULL;
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    print_r(sqlsrv_errors());
} else {
    echo "Migration successful.";
}
