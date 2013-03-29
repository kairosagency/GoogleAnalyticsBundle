<?php

/**
 * Generic Server-Side Google Analytics PHP Client
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License (LGPL) as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA.
 *
 * Google Analytics is a registered trademark of Google Inc.
 *
 * @link      http://code.google.com/p/php-ga
 *
 * @license   http://www.gnu.org/licenses/lgpl.html
 * @author    Thomas Bachem <tb@unitedprototype.com>
 * @copyright Copyright (c) 2010 United Prototype GmbH (http://unitedprototype.com)
 */

namespace GoogleAnalytics\Lib;

use GoogleAnalytics\Lib\Internals\Util;
use GoogleAnalytics\Lib\Internals\Request\PageviewRequest;
use GoogleAnalytics\Lib\Internals\Request\EventRequest;
use GoogleAnalytics\Lib\Internals\Request\TransactionRequest;
use GoogleAnalytics\Lib\Internals\Request\ItemRequest;
use GoogleAnalytics\Lib\Internals\Request\SocialInteractionRequest;

class Tracker {

    /**
     * Google Analytics client version on which this library is built upon,
     * will be mapped to "utmwv" parameter.
     *
     * This doesn't necessarily mean that all features of the corresponding
     * ga.js version are implemented but rather that the requests comply
     * with these of ga.js.
     *
     * @link http://code.google.com/apis/analytics/docs/gaJS/changelog.html
     * @const string
     */
    const VERSION = '5.2.5'; // As of 25.02.2012


    /**
     * The configuration to use for all tracker instances.
     *
     * @var \GoogleAnalytics\Lib\Config
     */
    protected static $config;

    /**
     * Google Analytics account ID, e.g. "UA-1234567-8", will be mapped to
     * "utmac" parameter
     *
     * @see Internals\ParameterHolder::$utmac
     * @var string
     */
    protected $accountId;

    /**
     * Host Name, e.g. "www.example.com", will be mapped to "utmhn" parameter
     *
     * @see Internals\ParameterHolder::$utmhn
     * @var string
     */
    protected $domainName;

    /**
     * Whether to generate a unique domain hash, default is true to be consistent
     * with the GA Javascript Client
     *
     * @link http://code.google.com/apis/analytics/docs/tracking/gaTrackingSite.html#setAllowHash
     * @see Internals\Request\Request::generateDomainHash()
     * @var bool
     */
    protected $allowHash = true;

    /**
     * @var array
     */
    protected $customVariables = array();

    /**
     * @var \GoogleAnalytics\Lib\Campaign
     */
    protected $campaign;


    /**
     * @param string $accountId
     * @param string $domainName
     * @param \GoogleAnalytics\Lib\Config $config
     */
    public function __construct($accountId, $domainName, Config $config = null) {
        static::setConfig($config ? $config : new Config());

        $this->setAccountId($accountId);
        $this->setDomainName($domainName);
    }

    /**
     * @return \GoogleAnalytics\Lib\Config
     */
    public static function getConfig() {
        return static::$config;
    }

    /**
     * @param \GoogleAnalytics\Lib\Config $value
     */
    public static function setConfig(Config $value) {
        static::$config = $value;
    }

    /**
     * @param string $value
     */
    public function setAccountId($value) {
        if(!preg_match('/^(UA|MO)-[0-9]*-[0-9]*$/', $value)) {
            static::_raiseError('"' . $value . '" is not a valid Google Analytics account ID.', __METHOD__);
        }

        $this->accountId = $value;
    }

    /**
     * @return string
     */
    public function getAccountId() {
        return $this->accountId;
    }

    /**
     * @param string $value
     */
    public function setDomainName($value) {
        $this->domainName = $value;
    }

    /**
     * @return string
     */
    public function getDomainName() {
        return $this->domainName;
    }

    /**
     * @param bool $value
     */
    public function setAllowHash($value) {
        $this->allowHash = (bool)$value;
    }

    /**
     * @return bool
     */
    public function getAllowHash() {
        return $this->allowHash;
    }

    /**
     * Equivalent of _setCustomVar() in GA Javascript client.
     *
     * @link http://code.google.com/apis/analytics/docs/tracking/gaTrackingCustomVariables.html
     * @param \GoogleAnalytics\Lib\CustomVariable $customVariable
     */
    public function addCustomVariable(CustomVariable $customVariable) {
        // Ensure that all required parameters are set
        $customVariable->validate();

        $index = $customVariable->getIndex();
        $this->customVariables[$index] = $customVariable;
    }

    /**
     * @return \GoogleAnalytics\Lib\CustomVariable[]
     */
    public function getCustomVariables() {
        return $this->customVariables;
    }

    /**
     * Equivalent of _deleteCustomVar() in GA Javascript client.
     *
     * @param int $index
     */
    public function removeCustomVariable($index) {
        unset($this->customVariables[$index]);
    }

    /**
     * @param \GoogleAnalytics\Lib\Campaign $campaign Isn't really optional, but can be set to null
     */
    public function setCampaign(Campaign $campaign = null) {
        if($campaign) {
            // Ensure that all required parameters are set
            $campaign->validate();
        }

        $this->campaign = $campaign;
    }

    /**
     * @return \GoogleAnalytics\Lib\Campaign|null
     */
    public function getCampaign() {
        return $this->campaign;
    }

    /**
     * Equivalent of _trackPageview() in GA Javascript client.
     *
     * @link http://code.google.com/apis/analytics/docs/gaJS/gaJSApiBasicConfiguration.html#_gat.GA_Tracker_._trackPageview
     * @param \GoogleAnalytics\Lib\Page $page
     * @param \GoogleAnalytics\Lib\Session $session
     * @param \GoogleAnalytics\Lib\Visitor $visitor
     */
    public function trackPageview(Page $page, Session $session, Visitor $visitor) {
        $request = new PageviewRequest(static::$config);
        $request->setPage($page);
        $request->setSession($session);
        $request->setVisitor($visitor);
        $request->setTracker($this);
        $request->fire();
    }

    /**
     * Equivalent of _trackEvent() in GA Javascript client.
     *
     * @link http://code.google.com/apis/analytics/docs/gaJS/gaJSApiEventTracking.html#_gat.GA_EventTracker_._trackEvent
     * @param \GoogleAnalytics\Lib\Event $event
     * @param \GoogleAnalytics\Lib\Session $session
     * @param \GoogleAnalytics\Lib\Visitor $visitor
     */
    public function trackEvent(Event $event, Session $session, Visitor $visitor) {
        // Ensure that all required parameters are set
        $event->validate();

        $request = new EventRequest(static::$config);
        $request->setEvent($event);
        $request->setSession($session);
        $request->setVisitor($visitor);
        $request->setTracker($this);
        $request->fire();
    }

    /**
     * Combines _addTrans(), _addItem() (indirectly) and _trackTrans() of GA Javascript client.
     * Although the naming of "_addTrans()" would suggest multiple possible transactions
     * per request, there is just one allowed actually.
     *
     * @link http://code.google.com/apis/analytics/docs/gaJS/gaJSApiEcommerce.html#_gat.GA_Tracker_._addTrans
     * @link http://code.google.com/apis/analytics/docs/gaJS/gaJSApiEcommerce.html#_gat.GA_Tracker_._addItem
     * @link http://code.google.com/apis/analytics/docs/gaJS/gaJSApiEcommerce.html#_gat.GA_Tracker_._trackTrans
     *
     * @param \GoogleAnalytics\Lib\Transaction $transaction
     * @param \GoogleAnalytics\Lib\Session $session
     * @param \GoogleAnalytics\Lib\Visitor $visitor
     */
    public function trackTransaction(Transaction $transaction, Session $session, Visitor $visitor) {
        // Ensure that all required parameters are set
        $transaction->validate();

        $request = new TransactionRequest(static::$config);
        $request->setTransaction($transaction);
        $request->setSession($session);
        $request->setVisitor($visitor);
        $request->setTracker($this);
        $request->fire();

        // Every item gets a separate request,
        // see http://code.google.com/p/gaforflash/source/browse/trunk/src/com/google/analytics/v4/Tracker.as#312
        foreach($transaction->getItems() as $item) {
            // Ensure that all required parameters are set
            $item->validate();

            $request = new ItemRequest(static::$config);
            $request->setItem($item);
            $request->setSession($session);
            $request->setVisitor($visitor);
            $request->setTracker($this);
            $request->fire();
        }
    }

    /**
     * Equivalent of _trackSocial() in GA Javascript client.
     *
     * @link http://code.google.com/apis/analytics/docs/tracking/gaTrackingSocial.html#settingUp
     * @param \GoogleAnalytics\Lib\SocialInteraction $socialInteraction
     * @param \GoogleAnalytics\Lib\Page $page
     * @param \GoogleAnalytics\Lib\Session $session
     * @param \GoogleAnalytics\Lib\Visitor $visitor
     */
    public function trackSocial(SocialInteraction $socialInteraction, Page $page, Session $session, Visitor $visitor) {
        $request = new SocialInteractionRequest(static::$config);
        $request->setSocialInteraction($socialInteraction);
        $request->setPage($page);
        $request->setSession($session);
        $request->setVisitor($visitor);
        $request->setTracker($this);
        $request->fire();
    }

    /**
     * For internal use only. Will trigger an error according to the current
     * Config::$errorSeverity setting.
     *
     * @see Config::$errorSeverity
     * @param string $message
     * @param string $method
     */
    public static function _raiseError($message, $method) {
        $method = str_replace(__NAMESPACE__ . '\\', '', $method);
        $message = $method . '(): ' . $message;

        $errorSeverity = isset(static::$config) ? static::$config->getErrorSeverity() : Config::ERROR_SEVERITY_EXCEPTIONS;

        switch($errorSeverity) {
            case Config::ERROR_SEVERITY_SILENCE:
                // Do nothing
                break;
            case Config::ERROR_SEVERITY_WARNINGS:
                trigger_error($message, E_USER_WARNING);
                break;
            case Config::ERROR_SEVERITY_EXCEPTIONS:
                throw new Exception($message);
                break;
        }
    }

}

?>