<?php
/**
 * Status Manager
 * 管理訂單通知狀態，避免重複發送（改進 woocommerce-notify 的設計）
 */

if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Status_Manager {
    
    /**
     * 創建狀態列表
     * 
     * @param WC_Order $order 訂單物件
     * @param bool $reset 是否重置
     */
    public static function create($order, $reset = false) {
        if (!$order->get_meta('_moksa_notify_status_list') || $reset) {
            $list = array();
            
            if (function_exists('wc_get_order_statuses')) {
                foreach (wc_get_order_statuses() as $key => $status) {
                    $list[str_replace('wc-', '', $key)] = 'yet';
                }
            }
            
            $order->update_meta_data('_moksa_notify_status_list', $list);
            $order->save();
        }
    }
    
    /**
     * 更新狀態列表
     * 
     * @param WC_Order $order 訂單物件
     */
    public static function update($order) {
        $list = $order->get_meta('_moksa_notify_status_list');
        if ($list) {
            $status = $order->get_status();
            $list[$status] = 'done';
            $order->update_meta_data('_moksa_notify_status_list', $list);
            $order->save();
        }
    }
    
    /**
     * 檢查是否可以發送
     * 
     * @param WC_Order $order 訂單物件
     * @return bool
     */
    public static function maybe_send($order) {
        $list = $order->get_meta('_moksa_notify_status_list') ? $order->get_meta('_moksa_notify_status_list') : array();
        $status = $order->get_status();
        
        // 自訂訂單狀態
        if (!key_exists($status, $list)) {
            $list[$status] = 'done';
            return true;
        }
        
        // 如果狀態是 'yet'，可以發送
        if (isset($list[$status]) && $list[$status] === 'yet') {
            return true;
        }
        
        return false;
    }
    
    /**
     * 重置狀態（用於測試或重新發送）
     * 
     * @param WC_Order $order 訂單物件
     * @param string $status 訂單狀態
     */
    public static function reset_status($order, $status = null) {
        $list = $order->get_meta('_moksa_notify_status_list') ? $order->get_meta('_moksa_notify_status_list') : array();
        
        if ($status) {
            $list[$status] = 'yet';
        } else {
            // 重置所有狀態
            foreach ($list as $key => $value) {
                $list[$key] = 'yet';
            }
        }
        
        $order->update_meta_data('_moksa_notify_status_list', $list);
        $order->save();
    }
}

