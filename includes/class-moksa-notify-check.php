<?php
/**
 * Notify Check Class
 * 檢查觸發規則是否匹配
 */

if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Notify_Check {
    
    /**
     * 檢查規則是否匹配
     * 
     * @param array $rules 觸發規則
     * @param WC_Order $order 訂單物件
     * @return bool 是否匹配
     */
    public static function check_rules_match($rules, $order) {
        if (empty($rules) || !is_array($rules)) {
            return true; // 沒有規則時，預設匹配
        }
        
        // 所有規則都必須匹配（AND 邏輯）
        foreach ($rules as $rule) {
            if (!self::check_single_rule_match($rule, $order)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 檢查單一規則是否匹配
     * 
     * @param array $rule 單一規則
     * @param WC_Order $order 訂單物件
     * @return bool 是否匹配
     */
    private static function check_single_rule_match($rule, $order) {
        $type = $rule['type'] ?? '';
        $operator = $rule['operator'] ?? '';
        $value = $rule['value'] ?? '';
        
        if (empty($type) || empty($operator) || empty($value)) {
            return true; // 規則不完整時，預設匹配
        }
        
        // 支付方式規則
        if ($type === 'payment_method') {
            $order_payment = $order->get_payment_method();
            if ($operator === 'is') {
                return $order_payment === $value;
            } elseif ($operator === 'is_not') {
                return $order_payment !== $value;
            }
        }
        
        // 運送方式規則
        if ($type === 'shipping_method') {
            $shipping_method_id = '';
            foreach ($order->get_items('shipping') as $item_id => $item) {
                $shipping_method_id = $item->get_data()['method_id'];
                break;
            }
            
            if ($operator === 'is') {
                return $shipping_method_id === $value;
            } elseif ($operator === 'is_not') {
                return $shipping_method_id !== $value;
            }
        }
        
        // 訂單金額規則
        if ($type === 'order_total') {
            $order_total = floatval($order->get_total());
            $rule_value = floatval($value);
            
            switch ($operator) {
                case 'gt':
                    return $order_total > $rule_value;
                case 'gte':
                    return $order_total >= $rule_value;
                case 'eq':
                    return $order_total == $rule_value;
                case 'lte':
                    return $order_total <= $rule_value;
                case 'lt':
                    return $order_total < $rule_value;
                default:
                    return false;
            }
        }
        
        return false;
    }
}

