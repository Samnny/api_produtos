<?php

namespace Contatoseguro\TesteBackend\Service;

use Contatoseguro\TesteBackend\Config\DB;

class ProductService
{
    private \PDO $pdo;
    public function __construct()
    {
        $this->pdo = DB::connect();
    }

    public function getAll($adminUserId)
    {   
        $query = "
            SELECT p.*, c.title as category
            FROM product p
            INNER JOIN product_category pc ON pc.product_id = p.id
            INNER JOIN category c ON c.id = pc.cat_id
            WHERE p.company_id = {$adminUserId} 
        ";
        
        if (isset($_GET['active'])) {
            $active = $_GET['active'];
            $query .= " 
                AND p.active = :active 
            ";            
        }
        if (isset($_GET['category'])) {
            $category = $_GET['category'];
            $query .= " 
                AND category = :category 
            ";            
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
            if ($order === 'asc' || $order === 'desc') {
                $query .= " ORDER BY p.created_at $order";
            }         
        }
        
        $stm = $this->pdo->prepare($query);

        if (isset($_GET['active'])) {
            $stm->bindParam(':active', $active);
        }
        if (isset($_GET['category'])) {
            $stm->bindParam(':category', $category);
        }
       
        $stm->execute();
        return $stm;
    }

    public function getOne($id)
    {
        $stm = $this->pdo->prepare("
            SELECT *
            FROM product
            WHERE id = {$id}
        ");
        $stm->execute();

        return $stm;
    }

    public function insertOne($body, $adminUserId)
    {
        $stm = $this->pdo->prepare("
            INSERT INTO product (
                company_id,
                title,
                price,
                active
            ) VALUES (
                {$body['company_id']},
                '{$body['title']}',
                {$body['price']},
                {$body['active']}
            )
        ");
        if (!$stm->execute())
            return false;

        $productId = $this->pdo->lastInsertId();

        $stm = $this->pdo->prepare("
            INSERT INTO product_category (
                product_id,
                cat_id
            ) VALUES (
                {$productId},
                {$body['category_id']}
            );
        ");
        if (!$stm->execute())
            return false;

        $stm = $this->pdo->prepare("
            INSERT INTO product_log (
                product_id,
                admin_user_id,
                `action`
            ) VALUES (
                {$productId},
                {$adminUserId},
                'create'
            )
        ");

        return $stm->execute();
    }

    public function updateOne($id, $body, $adminUserId)
    {   

        $stm = $this->pdo->prepare("SELECT price FROM product WHERE id = :id");
        $stm->bindParam(':id', $id);
        $stm->execute();
        $currentValues = $stm->fetch();

        $currentPrice = $currentValues->price;

        $action = 'update';
        $changes = '';
            
        if ($body->price != $currentPrice) {
            $changes .= 'price, ';
        }
            
            
        $changes = rtrim($changes, ', ');

        $stm = $this->pdo->prepare("
            UPDATE product
            SET company_id = {$body['company_id']},
                title = '{$body['title']}',
                price = {$body['price']},
                active = {$body['active']}
            WHERE id = {$id}
        ");
        if (!$stm->execute())
            return false;

        $stm = $this->pdo->prepare("
            UPDATE product_category
            SET cat_id = {$body['category_id']}
            WHERE product_id = {$id}
        ");
        if (!$stm->execute())
            return false;

        
            
        $stm = $this->pdo->prepare("
            INSERT INTO product_log (
                product_id,
                admin_user_id,
                `action`
            ) VALUES (
                :product_id,
                :admin_user_id,
                :action
            )
        ");

        if (!$stm->execute(array(
            ':product_id' => $id,
            ':admin_user_id' => $adminUserId,
            ':action' => $action . ' (' . $changes . ')'
        ))) {
            return false;
        }

        //return $stm->execute();
    }

    public function deleteOne($id, $adminUserId)
    {
        $stm = $this->pdo->prepare("
            DELETE FROM product_category WHERE product_id = {$id}
        ");
        if (!$stm->execute())
            return false;
        
        $stm = $this->pdo->prepare("DELETE FROM product WHERE id = {$id}");
        if (!$stm->execute())
            return false;

        $stm = $this->pdo->prepare("
            INSERT INTO product_log (
                product_id,
                admin_user_id,
                `action`
            ) VALUES (
                {$id},
                {$adminUserId},
                'delete'
            )
        ");

        return $stm->execute();
    }

    public function getLog($id)
    {
        $stm = $this->pdo->prepare("
            SELECT pl.*, a.name 
            FROM product_log pl
            INNER JOIN admin_user a ON pl.admin_user_id = a.id
            WHERE product_id = {$id}
        ");
        $stm->execute();

        return $stm;
    }
}
