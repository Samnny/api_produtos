<?php

namespace Contatoseguro\TesteBackend\Controller;

use Contatoseguro\TesteBackend\Service\CompanyService;
use Contatoseguro\TesteBackend\Service\ProductService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ReportController
{
    private ProductService $productService;
    private CompanyService $companyService;
    
    public function __construct()
    {
        $this->productService = new ProductService();
        $this->companyService = new CompanyService();
    }
    
    public function generate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $adminUserId = $request->getHeader('admin_user_id')[0];
        
        $data = [];
        $data[] = [
            'Id do produto',
            'Nome da Empresa',
            'Nome do Produto',
            'Valor do Produto',
            'Categorias do Produto',
            'Data de Criação',
            'Logs de Alterações',
            'Última Alteração'
        ];
        
        $stm = $this->productService->getAll($adminUserId);
        $products = $stm->fetchAll();

        foreach ($products as $i => $product) {
            $stm = $this->companyService->getNameById($product->company_id);
            $companyName = $stm->fetch()->name;

            $stm = $this->productService->getLog($product->id);
            $productLogs = $stm->fetchAll();

            $formattedLogs = '<table>';
            $lastModification = '';

            foreach ($productLogs as $log) {
                $formattedLogs .= '<tr>';
                $formattedLogs .= '<td>' . $log->name . '</td>';
                $formattedLogs .= '<td>' . $log->action . '</td>';
                $formattedLogs .= '<td>' . $log->timestamp . '</td>';
                $formattedLogs .= '</tr>';
            }
            $formattedLogs .= '</table>';
        
            $formattedLogs = rtrim($formattedLogs, ', ');

            $updateLogs = array_filter($productLogs, function($log) {
                return strpos($log->action, 'update') !== false;
            });

            usort($updateLogs, function($a, $b) {
                return strtotime($b->timestamp) - strtotime($a->timestamp);
            });

            $lastUpdateLog = reset($updateLogs);
            if ($lastUpdateLog) {
                $lastModification = '(' . $lastUpdateLog->name . ', ' . $lastUpdateLog->action . ', ' . $lastUpdateLog->timestamp . ')';
            }

            $data[$i+1][] = $product->id;
            $data[$i+1][] = $companyName;
            $data[$i+1][] = $product->title;
            $data[$i+1][] = $product->price;
            $data[$i+1][] = $product->category;
            $data[$i+1][] = $product->created_at;
            $data[$i+1][] = $formattedLogs;
            $data[$i+1][] = $lastModification;
        }
        
        $report = "<table style='font-size: 10px;'>";
        foreach ($data as $row) {
            $report .= "<tr>";
            foreach ($row as $column) {
                $report .= "<td>{$column}</td>";
            }
            $report .= "</tr>";
        }
        $report .= "</table>";
        
        $response->getBody()->write($report);
        return $response->withStatus(200)->withHeader('Content-Type', 'text/html');
    }
}