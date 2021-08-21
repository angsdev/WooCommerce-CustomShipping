<?php

/*
Plugin Name: Custom Shipping
Plugin URI: https://github.com/angsdev/woocommerce-custom-shipping
Description: Provides custom shipping method
Version: 1.0.0
Author: Angel Quiñonez
Author Url: https://angsdev.github.io
License: GPL2s
Text Domain: CSM
*/

defined('ABSPATH') or die('Nothing is here');
if(!defined('WPINC')){ die; }

if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
        function custom_shipping_method(){
        if (!class_exists('Custom_Shipping')){
            class Custom_Shipping extends WC_Shipping_Method {

                protected $data = [];

                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct(){ 
                    $this->id                 = 'customShipping'; 
                    $this->method_title       = __('Custom Shipping', 'CSM');  
                    $this->method_description = __('Custom Shipping Method ', 'CSM');
                    $this->availability       = 'including';
                    $this->countries          = ['US', 'CA'];
                    $this->init();
                    $this->enabled            = $this->settings['enabled'];
                    $this->title              = $this->settings['title'];
                    $this->data = [
                        'login' => [
                            'username'     => $this->settings['username'],
                            'password'     => $this->settings['password'],
                            'busId'        => $this->settings['busId'],
                            'busRole'      => $this->settings['busRole'],
                            'paymentTerms' => $this->settings['paymentTerms']
                        ],
                        'details' => [
                            'serviceClass' => 'STD',
                            'typeQuery'    => 'QUOTE',
                            'pickupDate'   => date('Ymd', strtotime(date('Ymd').'+'.((date('w') == 5) ? 3 : ((date('w') == 6) ? 2 : 1)).' days')),
                            'productCode'  => 'DFQ'
                        ],
                        'originLocation' => [
                            'city'         => 'Venus',
                            'state'        => 'FL',
                            'postalCode'   => '33960',
                            'country'      => 'USA',
                            'locationType' => 'COMM'
                        ],
                        'destinationLocation' => [],
                        'listOfCommodities' => [
                            'stackable' => true,
                            'commodity' => []
                        ],
                        'serviceOpts' => [ 
                            'accOptions' => [] 
                        ] 
                    ];
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 **/
                function init(){
                    // Load the settings API
                    $this->init_form_fields(); 
                    $this->init_settings(); 
                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
                }

                /**
                 * Define settings field for this shipping
                 * @return void 
                **/
                function init_form_fields(){ 
                    $this->form_fields = [
                        'enabled' => [
                            'title'       => __('Shipping Method Status', 'CSM'),
                            'type'        => 'checkbox',
                            'description' => __('Checkbox to enable/disable this shipping method.', 'CSM'),
                            'default'     => 'yes'
                        ],
                        'title' => [
                            'title'       => __('Name/Title:', 'CSM'),
                            'type'        => 'text',
                            'description' => __('Name to be display on shipping section of website.', 'CSM'),
                            'default'     => __('Custom Shipping Method', 'CSM')
                        ],
                        'username' => [
                            'title'       => __('Username:', 'CSM'),
                            'type'        => 'text',
                            'description' => 'Username needed to consume the API.',
                            'default'     => 'Username example'
                        ],
                        'password' => [
                            'title'       => __('Password:', 'CSM'),
                            'type'        => 'password',
                            'description' => 'Password needed to consume the API.',
                            'default'     => 'Password example'
                        ],
                        'busId' => [
                            'title'       => __('Bus ID:', 'CSM'),
                            'type'        => 'number',
                            'description' => 'Bus id needed to consume the API.',
                            'default'     => 0
                        ],
                        'busRole' => [
                            'title'       => __('Bus Role:', 'CSM'),
                            'type'        => 'text',
                            'description' => 'Bus role needed to consume the API.',
                            'default'     => 'Third Party'
                        ],
                        'paymentTerms' => [
                            'title'       => __('Payment Terms:', 'CSM'),
                            'type'        => 'text',
                            'description' => 'Payment Terms needed to consume the API.',
                            'default'     => 'Prepaid'
                        ]
                    ];
                }

                public function calculate_shipping($package = []) {

                    $this->data['destinationLocation'] = [
                            'city' => $package['destination']['city'],
                            'state' => $package['destination']['state'],
                            'postalCode' => $package['destination']['postcode'],
                            'country' => (($package['destination']['country'] == 'US') ? 'USA' : 
                                         (($package['destination']['country'] == 'CA') ? 'CAN' : 
                                           $package['destination']['country'])),
                            'locationType' => WC()->session->get('locationType')
                    ];

                    $extraCost = ($this->data['destinationLocation']['locationType'] == 'HOME') ? 75 : 75;

                    $i = 0;
                    foreach($package['contents'] as $item_id => $values){ 
                        $product   =  $values['data']; 
                        $length[$i] =+ $product->get_length();
                        $width[$i]  =+ $product->get_width();
                        $height[$i] =+ $product->get_height();                   
                        $weight[$i] =+ $product->get_weight();
                        array_push($this->data['listOfCommodities']['commodity'],  
                            [
                                'packageLength' => $length[$i],
                                'packageWidth'  => $width[$i],
                                'packageHeight' => $height[$i],
                                'weight'        => $weight[$i],
                                'packageCode'   => 'SKD',
                                'handlingUnits' => $values['quantity']
                            ]
                        );
                        $i++;
                    }

                    $JSONRequest = json_encode($this->data);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.example');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $JSONRequest);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $response = json_decode(curl_exec($ch), true);
                    $cError = (empty(curl_error($ch))) ? false : true;
                    if($cError){
                        
                        $rate = [
                            'id'    => $this->id,
                            'label' => $this->title.': <br/>We can\'t validate shipping, please contact us.'
                        ];

                    } else{
                        
                        if($response['isSuccess']){

                            $stdCost = $response['pageRoot']['bodyMain']['rateQuote']['ratedCharges']['totalCharges']/100;
                            $rate = [
                                'id'    => $this->id,
                                'label' => $this->title,
                                'cost'  => ($stdCost + $extraCost)
                            ];
                        } else{
                            
                            $fError = $response['errors'][0]['field'];
                            $error = (isset($response['pageRoot']['returnText'])) ? $response['pageRoot']['returnText'] : $response['errors'][0]['message'];
                            if(($fError == 'serviceOpts.accOptions') || ($error == "We're sorry, your liftgate requirement exceeds liftgate specifications. Please call Customer Service 800-00-0000")){
                                $rate  = [
                                    'id'    => $this->id,
                                    'label' => $this->title.': <br/> It doesn\'t delivery price, please contact us.'
                                ];
                            } else{
                                $rate  = [
                                    'id'    => $this->id,
                                    'label' => $this->title.': <br/>'.$error
                                ];
                            }
                        }
                    }
                    curl_close($ch);
                    $this->add_rate($rate);
                }
            }
        }
    }
    add_action('woocommerce_shipping_init', 'custom_shipping_method');

    function add_custom_shipping_method($methods) {
        $methods[] = 'Custom_Shipping';
        return $methods;
    }
    
    add_filter('woocommerce_shipping_methods', 'add_custom_shipping_method');

} 

?>