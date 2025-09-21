2.0.0
=============

* Important changes:
    * Moved business logic to the model layer
    * Added service contracts for importing CSV data: `ImportInterface`, `ImportResultInterface`, etc.
    * Changed ACL `EPuzzle_ImportExport::csv` to `EPuzzle_ImportExport::csv_import`
    * Added security controller to download import reports
* New features:
    * Batching CSV files. Available in CLI command and REST API
    * Batching large CSV files before import process automatically

1.0.0
=============

* New features:
    * Import CSV data using rest API
    * Import CSV data using CLI command
