@ECHO OFF
SET BIN_TARGET=%~dp0/../hypercharge/json-schema-php/bin/validate-json
php "%BIN_TARGET%" %*
