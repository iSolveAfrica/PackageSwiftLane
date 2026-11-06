<?php
/**
 * Licence client replacement.
 *
 * The original file was an ionCube-encoded LicenseBox client (despite its name
 * it contains no money code). The application is delivered as readable source
 * owned by the project; no remote licence or auto-update service is used.
 * verify_purchase.php and verify_update.php keep working with the shapes below.
 */
class License2l6aspi3ekdz14bfnxwtugjycm05hqcdpcoddingproV75
{
    public function activate_license($licenseCode, $clientName)
    {
        return ['status' => true, 'message' => 'This installation does not require activation.'];
    }

    public function verify_license($timeBasedCheck = false)
    {
        return ['status' => true, 'message' => 'Verified'];
    }

    public function check_update()
    {
        return ['status' => false, 'message' => 'Updates are delivered through the git repository.', 'changelog' => '', 'update_id' => null, 'has_sql' => false, 'version' => null];
    }

    public function download_update($updateId, $hasSql, $version, $type = null, $clientName = null, $dbCredentials = [])
    {
        echo 'Updates are deployed from the repository (see README.md). Nothing was downloaded.';
        return false;
    }
}
