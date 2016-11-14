<?php

/*
 * Domain plugin for HiPanel
 *
 * @link      https://github.com/hiqdev/hipanel-module-domain
 * @package   hipanel-module-domain
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2015-2016, HiQDev (http://hiqdev.com/)
 */

namespace hipanel\modules\domain\models;

use Exception;
use hipanel\helpers\ArrayHelper;
use hipanel\helpers\StringHelper;
use hipanel\modules\client\models\Client;
use hipanel\modules\dns\models\Record;
use hipanel\modules\dns\validators\DomainPartValidator;
use hipanel\modules\domain\validators\NsValidator;
use hipanel\validators\DomainValidator;
use hiqdev\hiart\ErrorResponseException;
use Yii;
use yii\helpers\Html;

class Domain extends \hipanel\base\Model
{
    const STATE_OK = 'ok';
    const STATE_INCOMING = 'incoming';
    const STATE_OUTGOING = 'outgoing';
    const STATE_EXPIRED = 'expired';

    const DEFAULT_ZONE = 'com';

    public $authCode;

    public static $contactOptions = [
        'registrant',
        'admin',
        'tech',
        'billing',
    ];

    public static function stateOptions()
    {
        return [
            self::STATE_OK => Yii::t('hipanel:domain', 'Domains in «ok» state'),
            self::STATE_INCOMING => Yii::t('hipanel:domain', 'Incoming transfer domains'),
            self::STATE_OUTGOING => Yii::t('hipanel:domain', 'Outgoing transfer domains'),
            self::STATE_EXPIRED => Yii::t('hipanel:domain', 'Expired domains'),
        ];
    }

    use \hipanel\base\ModelTrait;

    /** {@inheritdoc} */
    public function rules()
    {
        return [
            [['id', 'zone_id', 'seller_id', 'client_id', 'remoteid', 'daysleft', 'prem_daysleft'],                      'integer'],
            [['domain', 'statuses', 'name', 'zone', 'state', 'lastop', 'state_label'],                                  'safe'],
            [['seller', 'seller_name', 'client', 'client_name'],                                                        'safe'],
            [['created_date', 'updated_date', 'transfer_date', 'expiration_date', 'expires', 'since', 'prem_expires'],  'date'],
            [['registered', 'operated'],                                                                                'date'],
            [['is_expired', 'is_served', 'is_holded', 'is_premium', 'is_secured', 'is_freezed', 'wp_freezed'],          'boolean'],
            [['premium_autorenewal', 'expires_soon', 'autorenewal', 'whois_protected'],                                 'boolean'],
            [['foa_sent_to'],                                                                                           'email'],
            [['url_fwval', 'mailval', 'parkval', 'soa', 'dns', 'counters'],                                             'safe'],
            [['registrant', 'admin', 'tech', 'billing'],                                                                'integer'],
            [['block', 'epp_client_id', 'nameservers', 'nsips'],                                                        'safe'],
            [['note'],                                    'safe',     'on' => ['set-note', 'default']],

            [['registrant', 'admin', 'tech', 'billing'],           'required', 'on' => ['set-contacts']],

            [['enable'],                                        'safe',     'on' => ['set-lock', 'set-whois-protect']],
            [['domain', 'autorenewal'],                   'safe',     'on' => 'set-autorenewal'],
            [['domain', 'whois_protected'],               'safe',     'on' => 'set-whois-protect'],
            [['domain', 'is_secured'],                    'safe',     'on' => 'set-lock'],
            [['domain'],                                  'safe',     'on' => ['sync', 'only-object']],
            [['id'], 'required', 'on' => [
                'enable-freeze', 'disable-freeze',
                'sync', 'only-object',
                'regen-password',
                'set-note',
                'set-autorenewal', 'set-whois-protect', 'set-lock',
                'push-with-pincode'
            ]],

            // Check domain
            [['domain'], DomainPartValidator::className(), 'message' => Yii::t('hipanel:domain', '\'{value}\' is not valid domain name'), 'on' => ['check-domain']],
            [['domain'], 'filter', 'filter' => function ($value) {
                if (strpos($value, '.') !== false) {
                    return substr($value, 0, strpos($value, '.'));
                } else {
                    return $value;
                }
            }, 'on' => 'check-domain'],
            [['domain'], 'required', 'on' => ['check-domain']],
            [['zone'], 'safe', 'on' => ['check-domain']],
            [['zone'], 'trim', 'on' => ['check-domain']],
            [['zone'], 'default', 'value' => static::DEFAULT_ZONE, 'on' => ['check-domain']],
            [['is_available'], 'boolean', 'on' => ['check-domain']],
            [['resource'], 'safe', 'on' => ['check-domain']], /// Array inside. Should be a relation hasOne

            // Domain transfer
            [['domain', 'password'], 'required', 'when' => function ($model) {
                return empty($model->domains);
            }, 'on' => ['transfer']],
            [['password'], 'required', 'when' => function ($model) {
                return empty($model->domains) && $model->domain;
            }, 'on' => ['transfer']],
            [['domains'], 'required', 'when' => function ($model) {
                return empty($model->domain) && empty($model->password);
            }, 'on' => ['transfer']],
            [['domain'], DomainValidator::class, 'on' => ['transfer']],
            [['password'], function ($attribute) {
                try {
                    $this->perform('CheckTransfer', ['domain' => $this->domain, 'password' => $this->password]);
                } catch (Exception $e) {
                    $this->addError($attribute, Yii::t('hipanel:domain', 'Wrong code: {message}', ['message' => $e->getMessage()]));
                }
            }, 'when' => function ($model) {
                return $model->domain;
            }, 'on' => ['transfer']],
            [['domain', 'password'], 'trim', 'on' => ['transfer']],

            // NSs
            [['domain', 'nameservers', 'nsips'],                   'safe',     'on' => 'set-nss'],
            [['nameservers', 'nsips'], 'filter', 'filter' => function ($value) {
                return !is_array($value) ? StringHelper::mexplode($value) : $value;
            }, 'on' => 'OLD-set-ns'],
            [['nameservers'], 'each', 'rule' => [DomainValidator::class], 'on' => 'OLD-set-ns'],
            [['nsips'], 'each', 'rule' => [NsValidator::class], 'on' => 'OLD-set-ns'],

            // Get zones
            [['dumb'], 'safe', 'on' => ['get-zones']],

            // Domain push
            [['receiver'], 'required', 'on' => ['push', 'push-with-pincode']],
            [['pincode'], 'required', 'on' => ['push-with-pincode']],
            [['pincode'], function ($attribute, $params) {
                try {
                    $response = Client::perform('CheckPincode', [$attribute => $this->$attribute, 'id' => Yii::$app->user->id]);
                } catch (Exception $e) {
                    $this->addError($attribute, Yii::t('hipanel:client', 'Wrong pincode'));
                }
            }, 'on' => ['push-with-pincode']],
            [['domain', 'sender', 'pincode'], 'safe', 'on' => ['push', 'push-with-pincode']],

            // Bulk set contacts
            [['id', 'domain'], 'safe', 'on' => ['bulk-set-contacts']],
            [['registrant', 'admin', 'tech', 'billing'], 'required', 'on' => ['bulk-set-contacts']],
        ];
    }

    /** {@inheritdoc} */
    public function attributeLabels()
    {
        return $this->mergeAttributeLabels([
            'epp_client_id'         => Yii::t('hipanel:domain', 'EPP client ID'),
            'remoteid'              => Yii::t('hipanel', 'Remote ID'),
            'domain'                => Yii::t('hipanel', 'Domain name'),
            'domain_like'           => Yii::t('hipanel', 'Domain name'),
            'note'                  => Yii::t('hipanel', 'Notes'),
            'nameservers'           => Yii::t('hipanel', 'Name Servers'),
            'transfer_date'         => Yii::t('hipanel:domain', 'Transfered'),
            'expiration_date'       => Yii::t('hipanel:domain', 'System Expiration Time'),
            'expires'               => Yii::t('hipanel:domain', 'Paid till'),
            'since'                 => Yii::t('hipanel:domain', 'Since Time'),
            'lastop'                => Yii::t('hipanel:domain', 'Last Operation'),
            'operated'              => Yii::t('hipanel:domain', 'Last Operation Time'),
            'whois_protected'       => Yii::t('hipanel:domain', 'WHOIS'),
            'is_secured'            => Yii::t('hipanel:domain', 'Protection'),
            'is_holded'             => Yii::t('hipanel:domain', 'On hold'),
            'is_freezed'            => Yii::t('hipanel:domain', 'Domain changes freezed'),
            'wp_freezed'            => Yii::t('hipanel:domain', 'Domain WHOIS freezed'),
            'foa_sent_to'           => Yii::t('hipanel:domain', 'FOA was sent to'),
            'is_premium'            => Yii::t('hipanel:domain', 'Is premium'),
            'prem_expires'          => Yii::t('hipanel:domain', 'Premium expires'),
            'prem_daysleft'         => Yii::t('hipanel:domain', 'Premium days left'),
            'premium_autorenewal'   => Yii::t('hipanel:domain', 'Premium autorenewal'),
            'url_fwval'             => Yii::t('hipanel:domain', 'Url forwarding'),
            'mailval'               => Yii::t('hipanel:domain', 'Mail'),
            'parkval'               => Yii::t('hipanel:domain', 'Parking'),
            'daysleft'              => Yii::t('hipanel:domain', 'Days left'),
            'is_expired'            => Yii::t('hipanel:domain', 'Is expired'),
            'expires_soon'          => Yii::t('hipanel:domain', 'Expires soon'),

            // domain transfer
            'password'              => Yii::t('hipanel:domain', 'Transfer (EPP) password'),

            // domain transfer
            'receiver'              => Yii::t('hipanel:domain', 'Receiver'),
            'pincode'               => Yii::t('hipanel:domain', 'Pin code'),

            // contacts
            'registrant' => Yii::t('hipanel:client', 'Registrant contact'),
            'admin' => Yii::t('hipanel:client', 'Admin contact'),
            'tech' => Yii::t('hipanel:client', 'Tech contact'),
            'billing' => Yii::t('hipanel:client', 'Billing contact'),
        ]);
    }

    public static function getZone($domain)
    {
        return substr($domain, strpos($domain, '.') + 1);
    }

    public function isFreezed()
    {
        return $this->is_freezed;
    }

    public function isWPFreezed()
    {
        return $this->wp_freezed;
    }

    public function scenarioCommands()
    {
        return [
            'get-zones' => ['aux', 'get-zones'],
        ];
    }

    public static function isDomainOwner($model)
    {
        return Yii::$app->user->is($model->client_id)
             || (!Yii::$app->user->can('resell') && Yii::$app->user->can('support') && Yii::$app->user->identity->seller_id === $model->client_id);
    }

    public static function notDomainOwner($model)
    {
        return Yii::$app->user->not($model->client_id) && (!Yii::$app->user->can('resell') && Yii::$app->user->can('support') && Yii::$app->user->identity->seller_id !== $model->client_id);
    }

    public function getDnsRecords()
    {
        return $this->hasMany(Record::className(), ['hdomain_id' => 'id']);
    }

    public function getTransferDataProvider()
    {
        $result = [
            'success' => null,
            'error' => null,
        ];

        $this->domains = trim($this->domains);
        $list = ArrayHelper::csplit($this->domains, "\n");
        foreach ($list as $key => $value) {
            $strCheck .= "\n$value";
            $strCheck = trim($strCheck);
            preg_match("/^([a-z0-9][0-9a-z.-]+)( +|\t+|,|;)(.*)/i", $value, $matches);
            if ($matches) {
                $domain = check::domain(trim(strtolower($matches[1])));
                if ($domain) {
                    $password = check::password(trim($matches[3]));
                    if ($password) {
                        $doms[$domain] = compact('domain', 'password');
                    } else {
                        $dom2err[$domain] = 'wrong input password';
                    }
                } else {
                    $dom2err[$value] = 'unknown error';
                }
            } else {
                $dom2err[$value] = 'empty code';
            }
        }

        return $result;
    }

    protected function checkDomainTransfer(array $data)
    {
        try {
            $response = $this->perform('CheckTransfer', $data, true);
        } catch (ErrorResponseException $e) {
            $response = $e->getMessage();
        }

        return $response;
    }

    public static function getCategories()
    {
        return [
            'adult' => [
                'sexy',
                'xxx',
                'porn',
                'adult',
                'sex',
            ],
            'business' => [
                'bar',
                'auto',
                'car',
                'cars',
                'rent',
                'security',
                'tickets',
            ],
            'geo' => [
                'miami',
                'london',
                'bayern',
                'budapest',
                'ae.org',
                'africa.com',
                'ar.com',
                'br.com',
                'cn.com',
                'com.se',
                'de.com',
                'eu.com',
                'gb.com',
                'gb.net',
                'gr.com',
                'hu.com',
                'hu.net',
                'jp.net',
                'jpn.com',
                'kr.com',
                'la',
                'mex.com',
                'no.com',
                'qc.com',
                'ru.com',
                'sa.com',
                'se.com',
                'se.net',
                'uk.com',
                'uk.net',
                'us.com',
                'us.org',
                'uy.com',
                'za.com',
                'kiev.ua',
                'com.ua',
                'su',
                'cc',
                'tv',
                'me',
                'co.com',
                'com.de',
                'in.net',
                'pw',
            ],
            'general' => [
                'com',
                'net',
                'name',
                'biz',
                'org',
                'info',
                'pro',
                'mobi',
            ],
            'nature' => [
                'flowers',
                'fishing',
                'space',
                'garden',
            ],
            'internet' => [
                'lol',
                'pics',
                'hosting',
                'click',
                'link',
                'wiki',
                'website',
                'host',
                'xyz',
                'feedback',
                'online',
                'site',
            ],
            'sport' => [
                'yoga',
                'diet',
                'fit',
                'rodeo',
            ],
            'society' => [
                'property',
                'college',
                'luxe',
                'vip',
                'abogado',
                'press',
                'blackfriday',
                'law',
                'work',
                'help',
                'theatre',
            ],
            'audio_music' => [
                'guitars',
                'hiphop',
                'audio',
            ],
            'home_gifts' => [
                'mom',
                'christmas',
                'cooking',
                'wedding',
                'gift',
                'casa',
                'design',
            ],
        ];
    }

    public static function getSpecial()
    {
        return [
            'popular' => ['com', 'net', 'org', 'info', 'biz', 'ru', 'me'],
            'promotion' => ['ru', 'xxx', 'com', 'net', 'org'],
        ];
    }

    public static function setIsotopeFilterValue($zone)
    {
        $getClass = function (array $arr) use ($zone) {
            $result = '';
            foreach ($arr as $cssClass => $items) {
                if (in_array($zone, $items, true)) {
                    $result = $cssClass;
                    break;
                }
            }
            return $result;
        };

        $result = sprintf('%s %s', $getClass(self::getCategories()), $getClass(self::getSpecial()));

        return $result;
    }

    public static function getCategoriesCount($zone, $data)
    {
        $i = 0;
        $categories = self::getCategories();
        if (!empty($data)) {
            foreach ($data as $item) {
                if (in_array($item['zone'], $categories[$zone], true)) {
                    ++$i;
                }
            }
        }

        return $i;
    }

    public function canRenew()
    {
        return in_array($this->state, [static::STATE_OK, static::STATE_EXPIRED], true);
    }
}
