<?php defined( 'ABSPATH' ) || exit;
/** Tools → "بيانات تجريبية Zad": creates demo categories, areas and fully filled services. */

add_action( 'admin_menu', function () {
	add_management_page( 'بيانات تجريبية', 'بيانات تجريبية Zad', 'manage_options', 'zad-demo', 'zad_demo_page' );
} );

function zad_demo_page() {
	echo '<div class="wrap"><h1>بيانات تجريبية</h1>';
	if ( isset( $_POST['zad_demo_go'] ) && check_admin_referer( 'zad_demo' ) && current_user_can( 'manage_options' ) ) {
		$n = zad_demo_import();
		flush_rewrite_rules();
		echo '<div class="notice notice-success"><p>تم إنشاء ' . (int) $n . ' خدمات تجريبية. <a href="' . esc_url( get_post_type_archive_link( 'zad_service' ) ) . '">عرض الخدمات</a></p></div>';
	}
	echo '<p>ينشئ أقساماً ومدناً وثلاث خدمات مكتملة البيانات لتجربة التصميم. يمكنك حذفها لاحقاً من قائمة الخدمات. لن يُكرَّر الإنشاء إن وُجدت خدمات بنفس العنوان.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'zad_demo' );
	echo '<p><button class="button button-primary" name="zad_demo_go" value="1">إنشاء البيانات التجريبية</button></p></form></div>';
}

function zad_demo_term( $name, $tax, $parent = 0 ) {
	$t = term_exists( $name, $tax );
	if ( ! $t ) {
		$t = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
	}
	return is_wp_error( $t ) ? 0 : (int) ( is_array( $t ) ? $t['term_id'] : $t );
}

function zad_demo_import() {
	$cats = array(
		'مكافحة الحشرات' => 'bug',
		'النظافة والتعقيم' => 'sparkle',
		'المياه والتسربات' => 'drop',
	);
	$cat_ids = array();
	foreach ( $cats as $name => $icon ) {
		$id = zad_demo_term( $name, 'service_cat' );
		if ( $id ) {
			update_term_meta( $id, 'zad_icon', $icon );
			$cat_ids[ $name ] = $id;
		}
	}
	$area_ids = array();
	foreach ( array( 'الرياض', 'جدة', 'الدمام' ) as $a ) {
		$area_ids[] = zad_demo_term( $a, 'service_area' );
	}
	foreach ( array( 'الملقا', 'النرجس', 'حطين', 'الياسمين', 'العارض' ) as $d ) {
		$area_ids[] = zad_demo_term( $d, 'service_area', $area_ids[0] );
	}

	$common_faq = array(
		array( 'q' => 'هل المعاينة مجانية؟', 'a' => 'نعم، المعاينة والتسعير مجانيان ويُحدَّد السعر النهائي قبل البدء.' ),
		array( 'q' => 'هل هناك ضمان؟', 'a' => 'نعم، ضمان مكتوب، وإذا عادت المشكلة خلال مدة الضمان نعالجها مجاناً.' ),
		array( 'q' => 'متى يصل الفني؟', 'a' => 'غالباً في نفس اليوم عند التواصل المبكر.' ),
	);
	$common_steps = array(
		array( 't' => 'التواصل والمعاينة', 'd' => 'تتواصل معنا ونحدد الحالة والمساحة. || الحالة، المساحة، الموعد' ),
		array( 't' => 'تحديد الحل والسعر', 'd' => 'نقترح الأنسب لك بسعر واضح.' ),
		array( 't' => 'التنفيذ', 'd' => 'يصل الفني بالمعدات والمواد المناسبة. || معدات، مواد آمنة' ),
		array( 't' => 'الفحص والضمان', 'd' => 'نفحص النتيجة ونسلّمك الضمان.' ),
	);
	$stats = array( array( 'n' => '+13', 'l' => 'سنة خبرة' ), array( 'n' => '+15,000', 'l' => 'عميل راضٍ' ), array( 'n' => '24/7', 'l' => 'استقبال الطلبات' ), array( 'n' => '12', 'l' => 'فني متخصص' ) );

	$services = array(
		array(
			'title' => 'شركة رش مبيدات بالرياض', 'cat' => 'مكافحة الحشرات', 'icon' => 'bug', 'tag' => 'رش محيطي ووقائي بمواد مرخصة — موجّه لا عشوائي',
			'badge' => 'الأكثر طلباً', 'price' => 150, 'warranty' => 'ضمان مكتوب', 'duration' => '30–90 دقيقة', 'response' => 'نفس اليوم', 'rating' => 4.9, 'reviews' => 320,
			'content' => '<p>خدمة رش المبيدات تناسب الوقاية العامة ومعالجة الإصابات المنتشرة على أكثر من نوع من الحشرات في وقت واحد. نبدأ دائماً بمعاينة تحدد نوع الإصابة ومصادرها قبل الرش.</p><h3>متى يكون الرش أفضل من الطعم؟</h3><p>حين تكون الإصابة منتشرة في أكثر من غرفة، أو توجد رطوبة عالية، وفي المعالجة الخارجية التي تمنع دخول الحشرات.</p>',
			'features' => "معاينة وتحديد الإصابة أولاً\nمبيدات مبطّنة طويلة الأثر\nرش محيطي خارجي\nرش داخلي موضعي\nإعادة مجانية خلال الضمان",
			'why' => array( array( 't' => 'مبيدات طويلة الأثر', 'd' => 'تثبت على الأسطح وتقاوم الرطوبة.' ), array( 't' => 'رش موجّه', 'd' => 'نركز على المسارات ونقاط الدخول.' ), array( 't' => 'معالجة محيطية', 'd' => 'تمنع دخول الحشرات من الخارج.' ), array( 't' => 'جدولة موسمية', 'd' => 'نكرر الرش الوقائي حسب الموسم.' ) ),
			'subs' => array( array( 't' => 'رش وقائي دوري', 'd' => 'للمنازل ذات الحدائق.' ), array( 't' => 'رش علاجي', 'd' => 'للإصابات المنتشرة.' ), array( 't' => 'رش المطاعم والمنشآت', 'd' => 'بمعايير صحية.' ) ),
			'tools' => array( array( 't' => 'مبيدات مرخصة', 'd' => 'مسجلة في الهيئة العامة للغذاء والدواء.' ), array( 't' => 'معدات رش احترافية', 'd' => 'توزيع دقيق وآمن.' ), array( 't' => 'ملابس وقاية', 'd' => 'أمان الفني والسكان.' ) ),
			'signs' => array( array( 't' => 'حديقة أو أرض فضاء ملاصقة', 'd' => 'أكثر عرضة لدخول الحشرات.' ), array( 't' => 'حشرات في أكثر من غرفة', 'd' => 'يدل على إصابة منتشرة.' ), array( 't' => 'رطوبة عالية', 'd' => 'تذيب الطعوم والمبيد المبطّن يقاومها.' ), array( 't' => 'نشاط موسمي', 'd' => 'مع دخول الصيف أو تقلب الطقس.' ) ),
			'harms' => array( array( 't' => 'دخول متكرر', 'd' => 'بلا حاجز محيطي تتكرر الإصابة.' ), array( 't' => 'تلويث الطعام', 'd' => 'الحشرات الزاحفة تنقل الجراثيم.' ), array( 't' => 'انتشار لغرف أخرى', 'd' => 'يصعّب المعالجة لاحقاً.' ) ),
			'safety' => array( array( 't' => 'آمن بعد الجفاف', 'd' => 'نلتزم بمدة قبل العودة عند الحاجة.' ), array( 't' => 'مواد مرخصة SFDA', 'd' => 'مسجلة ومصنفة عالمياً.' ), array( 't' => 'آمن مع الحيوانات الأليفة', 'd' => 'نراعي القطط والكلاب وأحواض السمك.' ) ),
			'after' => "تهوية المكان بعد المعالجة\nإبعاد الأطفال والحيوانات عن الأسطح حتى الجفاف\nمسح الأسطح الملامسة للطعام قبل استخدامها",
			'steps' => $common_steps,
			'factors' => array( array( 't' => 'نوع الآفة', 'd' => 'تختلف طريقة المعالجة والمواد.' ), array( 't' => 'مساحة المكان', 'd' => 'كل فئة مساحة لها سعر.' ), array( 't' => 'مرة واحدة أم دوري', 'd' => 'العقود الدورية أوفر.' ) ),
			'price_note' => 'عند التعاقد السنوي يحصل العميل على خصم شهرين مجاناً.',
			'prices' => "شقة | رش وقائي | 150 ريال | مناسب للوقاية الدورية | ضمان شهر\nشقة | رش علاجي | 250 ريال | للإصابات المنتشرة | ضمان 3 أشهر\nفيلا | رش وقائي | 300 ريال | محيط وحديقة | ضمان شهر\nفيلا | رش علاجي | 450 ريال | داخلي وخارجي | ضمان 3 أشهر",
			'packages' => "أساسية | 150 ريال | معاينة؛ رش داخلي؛ ضمان شهر\nشاملة | 300 ريال | معاينة؛ رش محيطي وداخلي؛ ضمان 3 أشهر\nدورية | من 120 ريال شهرياً | زيارات موسمية؛ أولوية في الحجز؛ ضمان مستمر",
			'warrantyrows' => array( array( 't' => 'إعادة مجانية خلال الضمان', 'd' => 'إن عادت الإصابة نعود ونعالج مجاناً.' ), array( 't' => 'ضمان مكتوب', 'd' => 'نحدد مدة الضمان كتابة قبل البدء.' ) ),
			'spec' => "المواد المستخدمة | مبيدات مبطّنة مرخصة SFDA\nمدة الفعالية | أثر وقائي يمتد أسابيع",
			'faq' => array_merge( array( array( 'q' => 'ما الفرق بين الرش والطعم؟', 'a' => 'الرش للإصابات المنتشرة والرطوبة والمعالجة الخارجية، والطعم للبؤر المحصورة.' ) ), $common_faq ),
		),
		array(
			'title' => 'شركة تنظيف مسابح بالرياض', 'cat' => 'النظافة والتعقيم', 'icon' => 'drop', 'tag' => 'تكنيس بالفاكيوم بدون تفريغ المياه',
			'badge' => '', 'price' => 150, 'warranty' => 'ضمان جودة', 'duration' => '2 – 4 ساعات', 'response' => 'معاينة نفس اليوم', 'rating' => 4.8, 'reviews' => 210,
			'content' => '<p>نوفر تنظيف المسابح بطريقتين حسب الحالة: تكنيس بالمكنسة الخاصة بدون تفريغ المياه، أو تنظيف عميق شامل للأرضيات والجدران مع التعقيم.</p>',
			'features' => "تكنيس بالفاكيوم بدون تفريغ\nتنظيف عميق شامل\nتعقيم بمواد آمنة\nاستبدال الفلاتر عند الحاجة\nعقود دورية من 4 إلى 8 زيارات",
			'why' => array( array( 't' => 'بدون تفريغ المياه', 'd' => 'توفير الوقت والماء.' ), array( 't' => 'خبرة في الفلاتر', 'd' => 'فنيون مدربون.' ), array( 't' => 'أسعار واضحة', 'd' => 'حسب المساحة.' ) ),
			'subs' => array( array( 't' => 'تكنيس المسبح', 'd' => 'شفط الطحالب والرواسب.' ), array( 't' => 'تنظيف عميق', 'd' => 'تفريغ وغسيل وتعقيم.' ), array( 't' => 'عقد دوري', 'd' => 'من 4 إلى 8 زيارات شهرياً.' ) ),
			'tools' => array( array( 't' => 'ماكينات فاكيوم حديثة', 'd' => 'تنظيف كامل بلا تفريغ.' ), array( 't' => 'مواد تعقيم آمنة', 'd' => 'تحافظ على توازن المياه.' ) ),
			'steps' => $common_steps, 'signs' => array(), 'harms' => array(), 'safety' => array(), 'after' => '',
			'factors' => array( array( 't' => 'مساحة المسبح', 'd' => 'صغير، متوسط، كبير.' ), array( 't' => 'نوع التنظيف', 'd' => 'تكنيس أو تفريغ كامل.' ) ),
			'prices' => "مسبح صغير | تنظيف عميق شامل | 250 ريال\nمسبح صغير | تكنيس | 150 - 200 ريال\nمسبح متوسط | تنظيف عميق شامل | 300 ريال\nمسبح كبير | تنظيف عميق شامل | 400 - 450 ريال\nعقد دوري | مسبح صغير 4-8 زيارات | من 600 ريال شهرياً",
			'packages' => '', 'warrantyrows' => array(), 'spec' => '', 'faq' => $common_faq,
		),
		array(
			'title' => 'كشف تسربات المياه بالرياض', 'cat' => 'المياه والتسربات', 'icon' => 'drop', 'tag' => 'أجهزة حديثة بدون تكسير',
			'badge' => 'جديد', 'price' => 200, 'warranty' => 'ضمان سنة', 'duration' => 'من ساعة إلى 3 ساعات', 'response' => 'خلال ساعتين', 'rating' => 4.9, 'reviews' => 180,
			'content' => '<p>نحدد مصدر التسرب بدقة باستخدام أجهزة حرارية وصوتية دون تكسير عشوائي، ثم نقدم تقريراً واضحاً.</p>',
			'features' => "أجهزة كشف حرارية وصوتية\nبدون تكسير عشوائي\nتقرير مصوّر\nضمان على الإصلاح",
			'why' => array( array( 't' => 'دقة عالية', 'd' => 'نحدد الموضع بدقة.' ), array( 't' => 'بدون تكسير', 'd' => 'نحافظ على الأرضيات.' ) ),
			'subs' => array(), 'tools' => array(), 'steps' => $common_steps, 'signs' => array(), 'harms' => array(), 'safety' => array(), 'after' => '', 'factors' => array(),
			'prices' => "كشف | تسرب داخلي | 200 ريال\nكشف | تسرب مسبح | 350 ريال", 'packages' => '', 'warrantyrows' => array(), 'spec' => '', 'faq' => $common_faq,
		),
	);

	$created = 0;
	foreach ( $services as $i => $sv ) {
		if ( get_page_by_title( $sv['title'], OBJECT, 'zad_service' ) ) {
			continue;
		}
		$pid = wp_insert_post( array(
			'post_type'    => 'zad_service',
			'post_status'  => 'publish',
			'post_title'   => $sv['title'],
			'post_content' => $sv['content'],
			'post_excerpt' => $sv['tag'],
			'menu_order'   => $i,
		) );
		if ( ! $pid || is_wp_error( $pid ) ) {
			continue;
		}
		$created++;
		wp_set_object_terms( $pid, array( (int) $cat_ids[ $sv['cat'] ] ), 'service_cat' );
		wp_set_object_terms( $pid, array_filter( $area_ids ), 'service_area' );
		$meta = array(
			'tagline' => $sv['tag'], 'icon' => $sv['icon'], 'badge' => $sv['badge'], 'price' => $sv['price'], 'price_unit' => 'ريال', 'warranty' => $sv['warranty'],
			'duration' => $sv['duration'], 'response' => $sv['response'], 'rating' => $sv['rating'], 'reviews' => $sv['reviews'], 'featured' => '1',
			'features' => $sv['features'], 'stats' => $stats, 'why' => $sv['why'], 'subs' => $sv['subs'], 'tools' => $sv['tools'], 'steps' => $sv['steps'],
			'factors' => $sv['factors'], 'prices' => $sv['prices'], 'faq' => $sv['faq'], 'signs' => $sv['signs'], 'harms' => $sv['harms'], 'safety' => $sv['safety'],
			'aftercare' => $sv['after'], 'price_note' => $sv['price_note'] ?? '', 'packages' => $sv['packages'], 'warrantyrows' => $sv['warrantyrows'], 'spec' => $sv['spec'],
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $pid, '_zad_' . $k, $v );
		}
	}
	return $created;
}
