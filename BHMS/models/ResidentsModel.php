<?php

require 'dbcreds.php';


/**
 * This class contains all the queries that will be returned to the ResidentsController file.
 *
 * @method residents_counter
 * @method add_new_tenant
 * @method get_last_inserted_tenant_id
 * @method appliance_tenID
 * @method residents_data
 * @method edit_tenant
 * @method deleteTenantById
 * @method get_appliances
 * @method get_occupancy
 * @method get_rooms
 * @method updateOccupancy
 * @method delete_occupancy
 * @method residents_data_Active
 * @method residents_data_Inactive
 * @method residents_data_Evicted
 * @method residents_data_Name
 * @method residents_data_Search
 * @method evictTenant
 * @method query_room_info
 * @class ResidentsModel
 * @extends dbcreds
 */
class ResidentsModel extends dbcreds{

    /**
     * Get data for total number of residents
     * 
     * @method residents_counter
     * @param none
     * @return $result
     */
    public static function residents_counter() {
        // Use self to access static variables within the static method
        $conn = self::get_connection();
    
        $query = "SELECT COUNT(*) AS count FROM tenant WHERE isRenting = 1 AND isDeleted = 0";
        $stmt = $conn->query($query);
    
        if ($stmt === false) {
            die("Error executing query: " . $conn->error);
        }
    
        $row = $stmt->fetch_assoc();
        $result = $row['count'];
    
        $stmt->close();
        $conn->close();
    
        return $result;
        
    }

    /**
     * Add a new tenant to the database
     * 
     * @method add_new_tenant
     * @param $new_tenant, $appliances
     * @return true
     */
    public static function add_new_tenant($new_tenant, $appliances) {

        $conn = self::get_connection();
    
        $query = $conn->prepare("INSERT INTO tenant (
            tenFname, 
            tenLname, 
            tenMI, 
            tenHouseNum, 
            tenSt, 
            tenBrgy, 
            tenCityMun, 
            tenProvince, 
            tenContact, 
            tenBdate, 
            tenGender, 
            emContactFname, 
            emContactLname, 
            emContactMI, 
            emContactNum, 
            isRenting
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0);");
    
        if ($query === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
    
        $query->bind_param(
            'sssssssssssssss',
            $new_tenant['tenFname'], $new_tenant['tenLname'], $new_tenant['tenMI'],
            $new_tenant['tenHouseNum'], $new_tenant['tenSt'], $new_tenant['tenBrgy'],
            $new_tenant['tenCityMun'], $new_tenant['tenProvince'], $new_tenant['tenContact'],
            $new_tenant['tenBdate'], $new_tenant['tenGender'], $new_tenant['emContactFname'],
            $new_tenant['emContactLname'], $new_tenant['emContactMI'], $new_tenant['emContactNum']
        );
    
        if ($query->execute()) {
            $tenantID = $conn->insert_id;
            $query->close();
    
            foreach($appliances as $appliance) {
                $appInfo = $appliance['appInfo'];
                $appQuery = $conn->prepare("INSERT INTO appliance (tenID, appInfo, appRate) VALUES (?, ?, ?)");
                
                if ($appQuery === false) {
                    throw new Exception("Prepare failed for appliance: " . $conn->error);
                }
    
                $appRate = 100.00; // assuming a fixed rate, adjust if needed
                $appQuery->bind_param('isd', $tenantID, $appInfo, $appRate);
    
                if (!$appQuery->execute()) {
                    $appQuery->close();
                    $conn->close();
                    throw new Exception("Execution failed for appliance: " . $appQuery->error);
                }
    
                $appQuery->close();
            }
    
            $conn->close();
            return true;
        } else {
            $query->close();
            $conn->close();
            throw new Exception("Execution failed: " . $query->error);
        }
    }
    
    /**
     * Get the last inserted tenant ID
     * 
     * @method get_last_inserted_tenant_id
     * @param none
     * @return $last_id
     */
    public static function get_last_inserted_tenant_id() {
        // Use self to access static variables within the static method
        $conn = self::get_connection();
    
        try {
            $sql = "SELECT MAX(tenID) AS last_id FROM tenant";
            $stmt = $conn->prepare($sql);
    
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
    
            $stmt->execute();
            $stmt->bind_result($last_id);
            $stmt->fetch();
            $stmt->close();
            $conn->close();
    
            return $last_id;
        } catch (Exception $e) {
            // Handle any errors
            echo "Error: " . $e->getMessage();
            return null;
        }
    }

    /**
     * Insert appliances into the database
     * 
     * @method appliance_tenID
     * @param $appliances, $last_id
     * @return true
     */
    public static function appliance_tenID($appliances, $last_id) {
        $conn = self::get_connection();
    
        try {
            $sql = "INSERT INTO appliance (tenID, appInfo) VALUES (?, ?)";
    
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
    
            foreach ($appliances as $applianceInfo) {
                $stmt->bind_param("is", $last_id, $applianceInfo);

                if (!$stmt->execute()) {
                    throw new Exception("Execution failed: " . $stmt->error);
                }
            }
    
            $stmt->close();
            $conn->close();
    
            return true; // Return true if insertion is successful
    
        } catch (Exception $e) {
            // Handle any errors
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Get all tenants from the database
     * 
     * @method residents_data
     * @param none
     * @return $tenants
     * @deprecated This method is deprecated. Use residents_table_data instead.
     */
    public static function residents_data(){
        try {
            // Use self to access static variables within the static method
            $conn = self::get_connection();
    
            $query = "SELECT * FROM tenant WHERE isDeleted = 0  ORDER BY isRenting=1 DESC";
            $result = $conn->query($query);

            if ($result === false) {
                throw new Exception("Query failed: " . $conn->error);
            }
    
            $tenants = [];
            while ($row = $result->fetch_assoc()) {
                $tenants[] = $row;
            }
    
            $result->free();
            $conn->close();
    
            // Return the array of tenants
            return $tenants;
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    
            // Return an empty array to indicate failure
            return [];
        }
    }

    /**
     * Edit tenant information
     * 
     * @method edit_tenant
     * @param $editTenantData, $editAppliances
     * @return true
     */
    public static function edit_tenant($editTenantData, $editAppliances) {
        // Extract tenant ID from the tenant data
        $tenantID = $editTenantData['Edit-tenID'];

        // Use self to access static variables within the static method
        $conn = self::get_connection();

        // Prepare the UPDATE query for tenant
        $query = $conn->prepare("UPDATE tenant SET 
            tenFname = ?, 
            tenLname = ?, 
            tenMI = ?, 
            tenHouseNum = ?, 
            tenSt = ?, 
            tenBrgy = ?, 
            tenCityMun = ?, 
            tenProvince = ?, 
            tenContact = ?, 
            tenBdate = ?, 
            tenGender = ?, 
            emContactFname = ?, 
            emContactLname = ?, 
            emContactMI = ?, 
            emContactNum = ? 
        WHERE tenID = ?");

        if ($query === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // Bind parameters to the prepared statement
        $query->bind_param(
            'sssssssssssssssi',
            $editTenantData['Edit-tenFname'], $editTenantData['Edit-tenLname'], $editTenantData['Edit-tenMI'],
            $editTenantData['Edit-tenHouseNum'], $editTenantData['Edit-tenSt'], $editTenantData['Edit-tenBrgy'],
            $editTenantData['Edit-tenCityMun'], $editTenantData['Edit-tenProvince'], $editTenantData['Edit-tenContact'],
            $editTenantData['Edit-tenBdate'], $editTenantData['Edit-tenGender'], $editTenantData['Edit-emContactFname'],
            $editTenantData['Edit-emContactLname'], $editTenantData['Edit-emContactMI'], $editTenantData['Edit-emContactNum'],
            $tenantID
        );

        if (!$query->execute()) {
            throw new Exception("Execution failed: " . $query->error);
        }

        $query->close();

        // Use prepared statement for DELETE operation to prevent SQL injection
        $deleteQuery = $conn->prepare("DELETE FROM appliance WHERE tenID = ?");
        if ($deleteQuery === false) {
            throw new Exception("Prepare failed for DELETE: " . $conn->error);
        }

        $deleteQuery->bind_param('i', $tenantID);
        if (!$deleteQuery->execute()) {
            throw new Exception("Execution failed for DELETE: " . $conn->error);
        }

        $deleteQuery->close();

        // Insert appliances
        foreach ($editAppliances as $appliance) {
            $appInfo = $appliance['appInfo'];
            $appQuery = $conn->prepare("INSERT INTO appliance (tenID, appInfo, appRate) VALUES (?, ?, ?)");

            if ($appQuery === false) {
                throw new Exception("Prepare failed for appliance: " . $conn->error);
            }

            $appRate = 100.00; // assuming a fixed rate, adjust if needed
            $appQuery->bind_param('isd', $tenantID, $appInfo, $appRate);

            if (!$appQuery->execute()) {
                throw new Exception("Execution failed for appliance: " . $appQuery->error);
            }

            $appQuery->close();
        }

        $conn->close();
        return true;
    }

    /**
     * Delete a tenant by ID
     * 
     * @method deleteTenantById
     * @param $tenantIdToDelete
     * @return true
     */
    public static function deleteTenantById($tenantIdToDelete) {
        try {
            // Create a connection to the database
            $conn = self::get_connection();
    
            // First Query: Update tenant
            $stmt1 = $conn->prepare("UPDATE tenant SET isDeleted = 1 WHERE tenID = ?;");
            $stmt1->bind_param("i", $tenantIdToDelete);
            $success1 = $stmt1->execute();
            $stmt1->close();
    
            // Second Query: Update occupancy
            $stmt2 = $conn->prepare("UPDATE occupancy SET isDeleted = 1 WHERE tenID = ?;");
            $stmt2->bind_param("i", $tenantIdToDelete);
            $success2 = $stmt2->execute();
            $stmt2->close();
    
            // Close the connection
            $conn->close();
    
            // Return true if both deletions were successful
            return $success1 && $success2;
    
        } catch (Exception $e) {
            // Optionally log the exception $e->getMessage();
            // Return false to indicate failure
            return false;
        }
    }

    /**
     * get appliances of a tenant by tenantID
     * 
     * @method get_appliances
     * @param $tenantID
     * @return $appliancesrue
     */
    public static function get_appliances($tenantID){
        try {
            // Use self to access static variables within the static method
            $conn = self::get_connection();
    
            // Prepare the SQL query to get all appliances for a specific tenant
            $stmt = $conn->prepare("SELECT * FROM appliance WHERE tenID = ?");
            $stmt->bind_param("i", $tenantID);
            $stmt->execute();
    
            $result = $stmt->get_result();
    
            $appliances = [];
            while ($row = $result->fetch_assoc()) {
                $appliances[] = $row;
            }
    
            $result->free();
            $stmt->close();
            $conn->close();
    
            // Return the array of appliances
            return $appliances;
        } catch (Exception $e) {
            // Log the error to a file or handle it as needed
            error_log("Error getting appliances: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    
            // Return an empty array to indicate failure
            return [];
        }
    }

    /**
     * Get occupancy of a tenant by tenantID
     * 
     * @method get_occupancy
     * @param $tenantID
     * @return $occupancy
     */
    public static function get_occupancy($tenantID){
        try {
            // Use self to access static variables within the static method
            $conn = self::get_connection();
    
            $stmt = $conn->prepare("
                SELECT occTypeName, occupancy.occTypeID, roomID,occDateStart,occDateEnd,occupancy.tenID,occupancyID, tenFname, tenMI, tenLname,occupancyRate 
                FROM tenant, occupancy, occupancy_type 
                WHERE tenant.tenID = occupancy.tenID 
                AND tenant.tenID = ? 
                AND occupancy.occTypeID = occupancy_type.occTypeID 
                AND occupancy.isDeleted = 0
                ORDER BY occupancy.occDateStart DESC
            ");
            $stmt->bind_param("i", $tenantID);
            $stmt->execute();
            $result = $stmt->get_result();
    
            // Fetch all rows as an associative array
            $occupancy = [];
            while ($row = $result->fetch_assoc()) {
                $occupancy[] = $row;
            }
    
            // Free the result
            $result->free();
            $stmt->close();
            $conn->close();
    
            // Return the array of appliances
            return $occupancy;
        } catch (Exception $e) {
            // Log the error to a file or handle it as needed
            error_log("Error getting occupancy: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    
            // Return an empty array to indicate failure
            return [];
        }
    }

    /**
     * Get all rooms from the database
     * 
     * @method get_rooms
     * @param none
     * @return $results
     */
    public static function get_rooms(){
        
        $conn = self::get_connection();
        $query = "SELECT * FROM room WHERE isDeleted = 0";
        $stmt = $conn->query($query);

        if ($stmt === false) {
            die("Error executing query: " . $conn->error);
        }

        $results = [];
        while ($row = $stmt->fetch_assoc()) {
            $results[] = $row;
        }

        $stmt->close();
        $conn->close();

        return $results;
    }

    /**
     * Edit occupancy information
     * 
     * @method updateOccupancy
     * @param $editInfo
     * @return true
     */
   public static function updateOccupancy($editInfo){
    $conn = self::get_connection();
        $query = $conn->prepare("UPDATE occupancy SET roomID = ?, occDateStart = ?, occDateEnd = ? WHERE occupancyID = ?");

        if ($query === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $query->bind_param(
            'sssi', 
            $editInfo['roomID'], $editInfo['occDateStart'], 
            $editInfo['occDateEnd'], $editInfo['occupancyID']
        );

        if (!$query->execute()) {
            $query->close();
            $conn->close();
            throw new Exception("Execute failed: " . $query->error);
            return false;
        } else {
            $query->close();
            $conn->close();
            return true;
        }
   }

    /**
      * Delete an occupancy by occupancyID
      * 
      * @method delete_occupancy
      * @param $delOccInfo
      * @return true
      */
   public static function delete_occupancy($delOccInfo){
        $conn = self::get_connection();
        $query = $conn->prepare("UPDATE occupancy SET isDeleted = 1 WHERE occupancyID = ?");

        if ($query === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $query->bind_param('i', $delOccInfo);

        if (!$query->execute()) {
            throw new Exception("Execute failed: " . $query->error);
        }

        $query->close();
        $conn->close();

        return true;
   }

    /**
     * Get all active residents from the database
     * 
     * @method residents_data_Active
     * @param none
     * @return $results
     */
   public static function residents_data_Active(){
        $conn = self::get_connection();
        $query = "SELECT * FROM tenant WHERE isRenting = 1 AND isDeleted = 0 ORDER BY isRenting DESC ";
        $stmt = $conn->query($query);

        if ($stmt === false) {
            die("Error executing query: " . $conn->error);
        }

        $results = [];
        while ($row = $stmt->fetch_assoc()) {
            $results[] = $row;
        }

        $stmt->close();
        $conn->close();

        return $results;
    }

    /**
     * Get all inactive residents from the database
     * 
     * @method residents_data_Inactive
     * @param none
     * @return $results
     */
    public static function residents_data_Inactive(){
        $conn = self::get_connection();
        $query = "SELECT * FROM tenant WHERE isRenting = 0 AND isDeleted = 0 ORDER BY isRenting DESC";
        $stmt = $conn->query($query);

        if ($stmt === false) {
            die("Error executing query: " . $conn->error);
        }

        $results = [];
        while ($row = $stmt->fetch_assoc()) {
            $results[] = $row;
        }

        $stmt->close();
        $conn->close();

        return $results;
    }

    /**
     * Get all evicted residents from the database
     * 
     * @method residents_data_Evicted
     * @param none
     * @return $results
     */
    public static function residents_data_Evicted(){
        $conn = self::get_connection();
        $query = "SELECT * FROM tenant WHERE isRenting = 2 AND isDeleted = 0 ORDER BY isRenting DESC";
        $stmt = $conn->query($query);

        if ($stmt === false) {
            die("Error executing query: " . $conn->error);
        }

        $results = [];
        while ($row = $stmt->fetch_assoc()) {
            $results[] = $row;
        }

        $stmt->close();
        $conn->close();

        return $results;
    }
    
    /**
     * Get all residents from the database sorted by name
     * 
     * @method residents_data_Name
     * @param none
     * @return $results
     */
    public static function residents_data_Name(){
        $conn = self::get_connection();
        $query = "SELECT * FROM tenant WHERE isDeleted = 0 ORDER BY tenFname ASC";
        $stmt = $conn->query($query);

        if ($stmt === false) {
            die("Error executing query: " . $conn->error);
        }

        $results = [];
        while ($row = $stmt->fetch_assoc()) {
            $results[] = $row;
        }

        $stmt->close();
        $conn->close();

        return $results;
   }

    /**
     * Get all residents from the database sorted by search
     * 
     * @method residents_data_Search
     * @param $search
     * @return $results
     */
   public static function residents_data_Search($search){
        $conn = self::get_connection();
        $query = "SELECT * FROM tenant WHERE LOWER(CONCAT(tenFname,tenLname,tenMI)) LIKE LOWER('%$search%') AND isDeleted = 0 ORDER BY tenLname ASC";
        $stmt = $conn->query($query);

        if ($stmt === false) {
            die("Error executing query: " . $conn->error);
        }

        $results = [];
        while ($row = $stmt->fetch_assoc()) {
            $results[] = $row;
        }

        $stmt->close();
        $conn->close();

        return $results;
   }

    /**
     * Evict a tenant
     * 
     * @method evictTenant
     * @param $evictInfo
     * @return true
     */
    public static function evictTenant($evictInfo) {
        $conn = self::get_connection();
        $query = $conn->prepare("UPDATE occupancy SET occDateEnd = CURRENT_DATE() WHERE tenID = ? AND occDateStart = ( SELECT occDateStart FROM occupancy WHERE tenID = ? ORDER BY occDateStart DESC LIMIT 1 ); ");

        if ($query === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $query->bind_param('ii', $evictInfo, $evictInfo);

        if (!$query->execute()) {
            $query->close();
            $conn->close();
            throw new Exception("Execute failed: " . $query->error);
            return false;
        } else {
            $query->close();
            $conn->close();
            return true;
        }
    }


    /**
     * 
     */
    public static function query_room_info($roomID){
        $conn = self::get_connection();
        $query = $conn->prepare("SELECT * FROM room WHERE roomID = ? AND isDeleted = 0");
        $query->bind_param('s', $roomID);
        $query->execute();
        $result = $query->get_result()->fetch_assoc();
        $query->close();
        $conn->close();
        return $result;
    }

}

?>