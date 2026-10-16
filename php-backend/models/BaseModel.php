<?php
/**
 * CareerSage AI - Base Model
 * Abstract base class for all models
 */

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
    }

    /**
     * Find record by ID
     * @param int $id
     * @return array|null
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->execute($sql, ['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Find record by field
     * @param string $field
     * @param mixed $value
     * @return array|null
     */
    public function findBy($field, $value) {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value";
        $stmt = $this->db->execute($sql, ['value' => $value]);
        return $stmt->fetch();
    }

    /**
     * Get all records
     * @param array $conditions
     * @param string $orderBy
     * @param int $limit
     * @return array
     */
    public function getAll($conditions = [], $orderBy = '', $limit = 0) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . $orderBy;
        }

        if ($limit > 0) {
            $sql .= " LIMIT " . $limit;
        }

        $stmt = $this->db->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Create new record
     * @param array $data
     * @return int|string
     */
    public function create($data) {
        $filteredData = $this->filterData($data);
        
        if (empty($filteredData)) {
            throw new Exception("No valid data provided for insertion.");
        }

        $fields = array_keys($filteredData);
        $placeholders = array_map(function($field) {
            return ":{$field}";
        }, $fields);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        $this->db->execute($sql, $filteredData);
        return $this->db->getLastInsertId();
    }

    /**
     * Update record
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $filteredData = $this->filterData($data);
        
        if (empty($filteredData)) {
            throw new Exception("No valid data provided for update.");
        }

        $setClauses = [];
        foreach ($filteredData as $field => $value) {
            $setClauses[] = "{$field} = :{$field}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . 
               " WHERE {$this->primaryKey} = :id";

        $filteredData['id'] = $id;
        $stmt = $this->db->execute($sql, $filteredData);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete record
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->execute($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Count records
     * @param array $conditions
     * @return int
     */
    public function count($conditions = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $stmt = $this->db->execute($sql, $params);
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    /**
     * Filter data based on fillable fields
     * @param array $data
     * @return array
     */
    protected function filterData($data) {
        if (empty($this->fillable)) {
            return $data;
        }

        $filtered = [];
        foreach ($this->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }

        return $filtered;
    }

    /**
     * Hide sensitive fields from results
     * @param array $data
     * @return array
     */
    protected function hideFields($data) {
        if (empty($this->hidden)) {
            return $data;
        }

        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * Validate required fields
     * @param array $data
     * @param array $required
     * @throws Exception
     */
    protected function validateRequired($data, $required) {
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Field '{$field}' is required.");
            }
        }
    }

    /**
     * Sanitize input data
     * @param mixed $data
     * @return mixed
     */
    protected function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        } elseif (is_string($data)) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }
}
?>