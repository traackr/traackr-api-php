<?php

namespace Traackr;

class AccountMgmt extends TraackrApiObject
{
    /**
     * Create a new customer key
     *
     * @param array $p
     * @return mixed
     * @throws MissingParameterException
     */
    public static function customerkeyCreate($p = array())
    {
        $account = new AccountMgmt();

        $account->checkRequiredParams($p, array('customer_name'));

        return $account->post(TraackrApi::$apiBaseUrl . 'account_mgmt/customerkey/create', $p);
    }

    /**
     * Get list of tags
     *
     * @param array $p
     * @return mixed
     * @throws MissingParameterException
     */
    public static function tagList($p = array())
    {
        $account = new AccountMgmt();

        $p = $account->addCustomerKey($p);
        
        $account->checkRequiredParams($p, array('customer_key'));

        // TraackrApiObject does not do this automatically for arbitrary fields,
        // so we keep this logic here.
        if (isset($p['tag_prefix_filter'])) {
            $p['tag_prefix_filter'] = is_array($p['tag_prefix_filter']) ?
                implode(',', $p['tag_prefix_filter']) : $p['tag_prefix_filter'];
        }

        return $account->get(TraackrApi::$apiBaseUrl . 'account_mgmt/tag/list', $p);
    }

    /**
     * Edit Customer Key
     *
     * @param array $p
     * @return mixed
     * @throws MissingParameterException
     */
    public static function customerKeyEdit($p = array())
    {
        $account = new AccountMgmt();

        $account->checkRequiredParams($p, array('customer_key'));

        return $account->post(TraackrApi::$apiBaseUrl . 'account_mgmt/customerkey/edit', $p);
    }

    /**
     * View Customer Key
     *
     * @param array $p
     * @return mixed
     * @throws MissingParameterException
     */
    public static function customerKeyView($p = array())
    {
        $account = new AccountMgmt();

        $account->checkRequiredParams($p, array('customer_key'));

        return $account->get(TraackrApi::$apiBaseUrl . 'account_mgmt/customerkey/view', $p);
    }

    /**
     * Delete Customer Key
     *
     * @param array $p
     * @return mixed
     * @throws MissingParameterException
     */
    public static function customerKeyDelete($p = array())
    {
        $account = new AccountMgmt();

        $account->checkRequiredParams($p, array('customer_key'));

        return $account->delete(TraackrApi::$apiBaseUrl . 'account_mgmt/customerkey/delete', $p);
    }
}