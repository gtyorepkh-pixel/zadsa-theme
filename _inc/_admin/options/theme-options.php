<?php if ( ! defined( 'ABSPATH' )  ) { die; } // Cannot access directly.



//

// Set a unique slug-like ID

//

$prefix = '_memo_theme_options';



//

// Create options

//

CSF::createOptions( $prefix, array(

  'menu_title' => 'إعدادات القالب',

  'menu_slug'  => 'memo-theme-options',

  'theme'  => 'light',

  'show_reset_all'  => false,

) );



CSF::createSection( $prefix, array(

    

    'id'  => 'genral_options',

    'title'  => 'إعدادات عامة',

    'fields' => array(



      array(

        'id'      => 'memopt_logo',

        'type'    => 'media',

        'title'   => 'شعار الموقع',

        'preview' => false,

      ),



      array(

        'id'      => 'memopt_phone',

        'type'    => 'Text',

        'title'   => 'رقم الجوال الأساسي',

      ),



      array(

        'id'      => 'memopt_whatsapp',

        'type'    => 'Text',

        'title'   => 'رقم الواتساب الأساسي',

      ),

      array(

        'id'      => 'memopt_mail',

        'type'    => 'Text',

        'title'   => 'البريد الإلكتروني',

      ),



      array(

        'id'      => 'memopt_address',

        'type'    => 'Text',

        'title'   => 'العنوان',

      ),





      array(

        'id'      => 'memopt_fb',

        'type'    => 'Text',

        'title'   => 'رابط فيسبوك',

      ),



      array(

        'id'      => 'memopt_tw',

        'type'    => 'Text',

        'title'   => 'رابط تويتر',

      ),



      array(

        'id'      => 'memopt_insta',

        'type'    => 'Text',

        'title'   => 'رابط انستجرام',

      ),



      array(

        'id'      => 'memopt_yt',

        'type'    => 'Text',

        'title'   => 'رابط يوتيوب',

      ),

    )

  ) );





CSF::createSection( $prefix, array(

    

    'id'  => 'home_options',

    'title'  => 'إعدادات الصفحة الرئيسية',

    'fields' => array(





    )

  ) );

  CSF::createSection( $prefix, array(

    

    'parent'  => 'home_options',

    'title'  => 'القسم الأول',

    'fields' => array(



        array(

            'id'      => 'memopt_sec1_h',

            'type'    => 'wp_editor',

            'title'   => 'العنوان والنص',

            'media_buttons' => false,

          ),

          array(

            'id'      => 'memopt_sec1_link1',

            'type'    => 'link',

            'title'   => 'الزر الأول',

          ),

          array(

            'id'      => 'memopt_sec1_link2',

            'type'    => 'link',

            'title'   => 'الزر الثاني',

          ),

          

    )

  ) );





  CSF::createSection( $prefix, array(

    

    'parent'  => 'home_options',

    'title'  => 'القسم الثاني',

    'fields' => array(

        array(

            'id'      => 'memopt_sec2_h',

            'type'    => 'wp_editor',

            'title'   => 'العنوان ووصف قصير',

         

            'media_buttons' => false,

          ),



          array(

            'id'     => 'memo_sec2_grp',

            'type'   => 'group',

            'title'  => 'الخدمات',

            'button_title'   => 'اضف خدمة جديدة',

            'fields' => array(

            

              array(

                'id'      => 'memopt_dsfd',

                'type'    => 'Text',

                'title'   => '',

              ),





              array(

                'id'      => 'memo_sec2_grp_img',

                'type'    => 'media',

                'title'   => 'الصورة',

              ),



              array(

                'id'      => 'memo_sec2_grp_link',

                'type'    => 'link',

                'title'   => 'زر الخدمة',

              ),

           

            ),

        

          ),

       

        

    )

  ) );



  CSF::createSection( $prefix, array(

    

    'parent'  => 'home_options',

    'title'  => 'القسم الثالث',

    'fields' => array(



        array(

            'id'      => 'memopt_sec3_h',

            'type'    => 'wp_editor',

            'title'   => 'العنوان والنص',

            'media_buttons' => false,

          ),

          

          array(

            'id'      => 'memo_sec3_img',

            'type'    => 'media',

            'title'   => 'الصورة',

          ),

          

    )

  ) );





  CSF::createSection( $prefix, array(

    

    'parent'  => 'home_options',

    'title'  => 'القسم الرابع',

    'fields' => array(

        array(

            'id'      => 'memopt_sec4_h',

            'type'    => 'wp_editor',

            'title'   => 'العنوان ووصف قصير',

            'media_buttons' => false,

          ),



          array(

            'id'     => 'memo_sec4_grp',

            'type'   => 'group',

            'title'  => 'المميزات',

            'button_title'   => 'اضف ميزة جديدة',

            'fields' => array(

      



              

              array(

                'id'      => 'memo_sec4_grp_h',

                'type'    => 'Text',

                'title'   => 'عنوان الميزة',

              ),



              array(

                'id'      => 'memo_sec4_grp_img',

                'type'    => 'media',

                'title'   => 'الصورة',

              ),

              





              array(

                'id'      => 'memo_sec4_grp_p',

                'type'    => 'textarea',

                'title'   => 'وصف الميزة',

              ),

      

            ),

        

          ),

       

        

    )

  ) );





  CSF::createSection( $prefix, array(

    

    'parent'  => 'home_options',

    'title'  => 'قسم المقالات',

    'fields' => array(


 array(
      'id'          => 'memo_sec15_grp_h',
      'type'        => 'select',
      'title'       => 'اختر المقالات',
      'chosen'      => true,
      'multiple'    => true,
      'sortable'    => true,
      'ajax'        => true,
      'options'     => 'posts',
      'placeholder' => 'اختر المقالات',
    ),
 



    )

  ) );



  CSF::createSection( $prefix, array(

    'id'  => 'about_options',

    'title'  => 'إعدادات صفحة من نحن',

    'fields' => array(





    )

  ) );



  CSF::createSection( $prefix, array(

    

    'parent'  => 'about_options',

    'title'  => 'القسم الأول',

    'fields' => array(



        array(

            'id'      => 'memopt_about_sec1_h',

            'type'    => 'wp_editor',

            'title'   => 'العنوان والنص',

            'media_buttons' => false,

          ),

          

          array(

            'id'      => 'memopt_about_sec1_img',

            'type'    => 'media',

            'title'   => 'الصورة',

          ),

          

    )

  ) );

  CSF::createSection( $prefix, array(

    

    'parent'  => 'about_options',

    'title'  => 'القسم الثاني',

    'fields' => array(



          array(

            'id'     => 'memopt_about_sec2_grp',

            'type'   => 'group',

            'title'  => 'المميزات',

            'button_title'   => 'اضف ميزة جديدة',

            'fields' => array(

      



              array(

                'id'      => 'memopt_about_sec2_grp_h',

                'type'    => 'Text',

                'title'   => 'عنوان الميزة',

              ),

              array(

                'id'      => 'memopt_about_sec2_grp_img',

                'type'    => 'media',

                'title'   => 'الصورة',

              ),

              



          



              array(

                'id'      => 'memopt_about_sec2_grp_p',

                'type'    => 'textarea',

                'title'   => 'وصف الميزة',

              ),

      

            ),

        

          ),

       

        

    )

  ) );

  CSF::createSection( $prefix, array(

    

    'parent'  => 'about_options',

    'title'  => 'القسم الثالث',

    'fields' => array(



        array(

            'id'      => 'memopt_about_sec3_h',

            'type'    => 'wp_editor',

            'title'   => 'العنوان والنص',

            'media_buttons' => false,

          ),

          

          array(

            'id'      => 'memopt_about_sec3_img',

            'type'    => 'media',

            'title'   => 'الصورة',

          ),

          

    )

  ) );



  CSF::createSection( $prefix, array(

    'id'  => 'services_options',

    'title'  => 'إعدادات صفحة خدماتنا',

    'fields' => array(

      array(

        'id'     => 'memopt_serv_grp',

        'type'   => 'group',

        'title'  => 'الخدمات',

        'button_title'   => 'اضف خدمة جديدة',

        'fields' => array(

  



          array(

            'id'      => 'memopt_serv_grp_h',

            'type'    => 'wp_editor',

            'title'   => 'العنوان والنص',

            'media_buttons' => false,

          ),



          array(

            'id'      => 'memopt_serv_grp_img',

            'type'    => 'media',

            'title'   => 'الصورة',

          ),

          



         array(

                'id'      => 'memopt_serv_grp_link',

                'type'    => 'link',

                'title'   => 'زر الخدمة',

              ),

  

        ),

    

      ),

    )

  ) );


  CSF::createSection( $prefix, array(

    


    'title'  => 'إعدادات صفحة أعمالنا',

    'fields' => array(


    array(

            'id'      => 'memopt_gallery_sec1_h',
            'type'    => 'wp_editor',
            'title'   => 'العنوان والنص',
            'media_buttons' => false,

          ),

          
      array(

        'id'     => 'memopt_gallery_grp',
        'type'   => 'group',
        'title'  => 'الخدمات',
        'button_title'   => 'اضف خدمة جديدة',
        'fields' => array(


          array(

            'id'      => 'memopt_gallery_grp_h',
            'type'    => 'text',
            'title'   => 'العنوان',

          ),

          array(

            'id'      => 'memopt_gallery_grp_link',
            'type'    => 'text',
            'title'   => 'الرابط',

          ),


          array(

            'id'      => 'memopt_gallery_grp_img_before',
            'type'    => 'media',
            'title'   => 'صورة قبل',

          ),

          
          array(

            'id'      => 'memopt_gallery_grp_img_after',
            'type'    => 'media',
            'title'   => 'صورة بعد',

          ),
  

        ),

    

      ),

          

    )

  ) );



  CSF::createSection( $prefix, array(

    'title'  => 'إعدادات الفوتر',

    'fields' => array(

 

      array(

        'id'      => 'memopt_footer_h',

        'type'    => 'wp_editor',

        'title'   => 'العنوان والنص',

      ),

    )

  ) );


  CSF::createSection( $prefix, array(

    'id'  => 'pest_options',

    'title'  => 'إعدادات مكتبة الافات ',

  'fields' => array(

        array(

            'id'      => 'memopt_pest_sec1_h',
            'type'    => 'wp_editor',
            'title'   => 'العنوان والنص',
            'media_buttons' => false,

          ),



    )

  ) );






  CSF::createSection( $prefix, array(

    'title'  => 'نسخ احتياطي',

    'fields' => array(

 

      array(

        'type' => 'backup',

      ),

    )

  ) );



  