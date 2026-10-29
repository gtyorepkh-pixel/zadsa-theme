import re,base64,html as H
from extract import *
ROOT='/home/user/zadsa-theme'
OUT=ROOT+'/docs/mockups/service-redesign-tanks-riyadh.html'
spans=top_level(h); N=[h[a:b] for a,b in spans]
def rd(p): return open(p,encoding='utf-8').read()
# ---------- CSS ----------
css_theme=''.join(rd(f'{ROOT}/assets/css/{f}') for f in ('zad.css','zad-work.css','zad-ix.css'))
vars_css=re.search(r'<style id="zad-vars">(.*?)</style>',h,re.S).group(1)
fonts_css=''
for w in (400,500,700,800):
    b=base64.b64encode(open(f'{ROOT}/assets/fonts/tajawal-{w}.woff2','rb').read()).decode()
    fonts_css+='@font-face{font-family:"Tajawal";font-style:normal;font-weight:%d;font-display:swap;src:url(data:font/woff2;base64,%s) format("woff2")}'%(w,b)
# ---------- helpers ----------
def unlazy(s):
    s=re.sub(r'<noscript>.*?</noscript>','',s,flags=re.S)
    def fix(m):
        t=m.group(0)
        t=re.sub(r'\ssrc="data:image/svg\+xml;base64,[^"]*"','',t)
        t=t.replace(' data-src=',' src=').replace(' data-srcset=',' srcset=').replace(' data-sizes=',' sizes=').replace(' data-lazyloaded="1"','')
        return t
    return re.sub(r'<img\b[^>]*>',fix,s)
def txt(s): return re.sub(r'\s+',' ',H.unescape(re.sub(r'<[^>]+>',' ',s))).strip()
def wc(s): return len(txt(s).split())
def tag(kind,label):
    return f'<span class="mk__tag mk__tag--{kind}">{label}</span>'
def wrap(kind,label,inner,cls=''):
    return f'<div class="mk {cls}">{tag(kind,label)}{inner}</div>'
N=[unlazy(x) for x in N]
hero,stats,intro,wiz,mp,content,cov,types,equip,steps,price,signs,harms,works,faq,qnet,related,gallery=N
cov=cov.replace('<div class="cov__st"><b>30 يوماً</b><span>ضمان مكتوب</span></div>','',1)
# ---------- hero: add «from» line ----------
# (no «from» pill: the estimator card already shows «يبدأ من 150 ريال»)
# ---------- pricing: last-updated line ----------
price=price.replace('<p class="pkgx__note">الأسعار تقديرية وتتحدد بعد المعاينة</p>','<p class="pkgx__note">الأسعار تقديرية وتتحدد بعد المعاينة</p><p class="pkgx__upd">خزانات المنشآت (مجمعات ومطاعم ومدارس ومستشفيات): عقود دورية وسعر بعد المعاينة. آخر تحديث للأسعار: <time datetime="2026-10-29">29 أكتوبر 2026</time></p>',1)
# ---------- steps: merge the equipment into the steps ----------
tools={1:'مضخات شفط وتفريغ',2:'فرش طويلة وأدوات زوايا، مع معدات تهوية ودخول آمن للخزانات المغلقة',3:'مواد تعقيم مخصصة للخزانات، تُطبق حسب تعليماتها',4:'أجهزة ضغط ماء مضبوطة حسب نوع الجدار'}
def add_tool(m):
    i=int(m.group(2)); s=m.group(0)
    if i in tools: s=s.replace('</li>',f'<p class="hsteps__tool"><b>الأداة:</b> {tools[i]}</p></li>',1)
    return s
def _tool(m):
    i=int(m.group(1)); li=m.group(0)
    return li.replace('</li>','<p class="hsteps__tool"><b>الأداة:</b> '+tools[i]+'</p></li>',1) if i in tools else li
steps=re.sub(r'<li><span class="hsteps__n">(\d)</span>.*?</li>',_tool,steps,flags=re.S)
harms=harms.replace('class="sec sec--cream"','class="sec sec--tint"',1)  # not cream: the price section after it is cream
steps=steps.replace('class="sec sec--cream"','class="sec"',1)  # white background, so it separates the hero price card from the cream price section
# ---------- needs section (merged) ----------
def demote(block,wrapper_cls):
    b=re.sub(r'^<section class="[^"]*"([^>]*)><div class="wrap">',r'<div class="need__grp \g<0>',block)
    return b
def inner_of(sec):  # inside of <section …><div class="wrap"> … </div></section>
    m=re.match(r'<section[^>]*><div class="wrap">(.*)</div></section>\s*$',sec,re.S); return m.group(1) if m else sec
def h2_to_h3(s): return s.replace('<h2','<h3',1).replace('</h2>','</h3>',1)
def head_to_group(inner):  # header.sec__head with h2 → h3 (keep eyebrow/p)
    return re.sub(r'<h2([^>]*)>(.*?)</h2>',r'<h3\1>\2</h3>',inner,count=1,flags=re.S)
w_in=head_to_group(inner_of(wiz)).replace('هل يحتاج خزانك تنظيفاً الآن؟','افحص خزانك في دقيقتين'); m_in=head_to_group(inner_of(mp))
s_in=re.search(r'<div class="cardgrid cardgrid--2">.*</div>\s*$',inner_of(signs),re.S).group(0)
hm_in=re.search(r'<div class="cardgrid">.*</div>\s*$',inner_of(harms),re.S).group(0)
needs=f'''<section class="sec" id="needs"><div class="wrap">
<header class="sec__head"><span class="eyebrow">علامات الحاجة للتنظيف</span><h2>هل يحتاج خزانك تنظيفاً الآن؟</h2>
<p>غالباً نعم إذا ظهرت أي علامة من العلامات التالية. افحص خزانك لتعرف الخطة المناسبة.</p></header>
<details class="need__details"><summary class="btn btn--accent">ابدأ الفحص: 6 أسئلة سريعة تعرف بعدها الخطة المناسبة لخزانك</summary><div class="need__grp ix ix--wiz" id="ix-wiz">{w_in}</div></details>
<div class="need__grp"><header class="sec__head"><h3>علامات تخبرك أنك تحتاج تنظيف خزانات بالرياض</h3></header>{s_in}</div>
</div></section>'''
# ---------- knowledge sections (regroup the 8 editor H2s) ----------
body=re.search(r'<div class="prose entry-content">(.*)</div></div></section>\s*$',content,re.S).group(1)
parts=re.split(r'(?=<h2 class="wp-block-heading">)',body)[1:]
P={i+1:p for i,p in enumerate(parts)}
def sub(p): return p.replace('<h2 class="wp-block-heading">','<h3 class="wp-block-heading">').replace('</h2>','</h3>')
def ksec(inner,idd=''):
    return f'<section class="sec"{(" id=%s"%chr(34)+idd+chr(34)) if idd else ""}><div class="wrap wrap--narrow"><div class="prose entry-content">{inner}</div></div></section>'
FAQ='http://zadksa.local/faq/'
def klinks(items,lead='تفاصيل أكثر:'):
    return '<p class="klinks">'+lead+' '+' · '.join(f'<a href="{u}">{t}</a>' for t,u in items)+'</p>'
review='<p class="kreview">المحتوى الفني في هذا القسم: <a href="http://zadksa.local/author/mahmoud/">محمود القحطاني</a>، محرر المحتوى الفني · آخر مراجعة: <time datetime="2026-10-29">29 أكتوبر 2026</time></p>'
P5='<h2 class="wp-block-heading">متى يحتاج الخزان صيانة أو عزلاً لا غسيلاً فقط؟</h2><p class="wp-block-paragraph">إذا عادت الرواسب أو الرائحة خلال أسابيع رغم تنظيف جيد، فالسبب غالباً في الخزان نفسه: <strong>شروخ في الجدار</strong>، أو <strong>جذور داخل الخزان الأرضي</strong>، أو <strong>صدأ وتقشر في الطبقة الداخلية</strong>، أو <strong>غطاء مكسور</strong>. نخبرك بالوضع الفعلي قبل البدء ونقدّم سعر الإصلاح أو العزل منفصلاً عن التنظيف. وبطء التصريف داخل البيت مشكلة أخرى تحتاج <a href="/services/drain-cleaning/riyadh/">تسليك مجاري بالرياض</a>.</p>'+klinks([('أفضل نوع عزل للخزانات الخرسانية',FAQ+'best-type-of-insulation-for-concrete-tanks/'),('هل الخزان الأرضي يحتاج عزلاً؟',FAQ+'does-the-underground-tank-need-insulation/'),('باقة العزل والصيانة وسعرها','#prices')])+'<p class="mk__todo">⟵ مقترح: صفحة مستقلة «عزل خزانات المياه» (أعلى طلب بحثي في الموضوع، صعوبة منخفضة) تُربط من هنا بدل تضخيم هذا القسم. لا رابط ميتاً الآن.</p>'
P4=P[4]+klinks([('أسباب ظهور رائحة كريهة لمياه الخزانات',FAQ+'appearance-of-a-foul-odor-in-the-water-tanks/')])
P8=P[8]+klinks([('هل يمكنني استخدام المياه فور الانتهاء من التنظيف؟',FAQ+'can-i-use-water-immediately-after-cleaning/')])+'<p class="mk__todo">⟵ يُضاف هنا مصدر رسمي لجودة مياه الشرب (جهة مختصة) — يحدده فريقك؛ لا أكتب مصدراً من عندي.</p>'
def H2(t): return f'<h2 class="wp-block-heading">{t}</h2>'
def H3(t): return f'<h3 class="wp-block-heading">{t}</h3>'
def Pp(t): return f'<p class="wp-block-paragraph">{t}</p>'
def UL(items,tag='ul'): return f'<{tag} class="wp-block-list">'+''.join(f'<li>{i}</li>' for i in items)+f'</{tag}>'
def TBL(head,rows): return '<figure class="wp-block-table"><table><thead><tr>'+''.join(f'<th>{h}</th>' for h in head)+'</tr></thead><tbody>'+''.join('<tr>'+''.join(f'<td>{c}</td>' for c in r)+'</tr>' for r in rows)+'</tbody></table></figure>'
def TODO(t): return f'<p class="mk__todo">⟵ {t}</p>'

P6s=P[6].replace('تتكرر في الرياض','تتكرر في الخزانات المكشوفة')
K1=ksec(P[1].replace('</h2>','</h2>'+review,1)+sub(P5)+sub(P6s))

K3=ksec(P[2]+sub(P[7]))
KA=ksec(H2('ماذا يخبرك لون الماء ورائحته؟ وهل المشكلة في الخزان أم في المواسير؟')
+Pp('قبل أن تطلب غسيل الخزان، انتبه لما يخرج من الحنفية: اللون والرائحة والطعم تدل غالباً على مصدر المشكلة. وليست كل مشكلة في الماء سببها الخزان؛ فبعضها من المواسير أو من الماء الداخل إليه، ولن يعالجها الغسيل مهما تكرر.')
+TBL(['ما تلاحظه','السبب الأرجح','هل يحلّه تنظيف الخزان؟'],[
['ماء أخضر أو طعم طحلبي','طحالب بسبب الضوء والحرارة','نعم، مع تظليل الخزان وإحكام الغطاء حتى لا تعود'],
['رائحة عفنة أو ترابية','رواسب عضوية وركود في القاع','نعم'],
['رائحة بيض فاسد','تحلل مواد في الخزان، وإن ظهرت في الماء الساخن فقط فالسبب غالباً السخان','تبدأ بالخزان، وتفحص السخان إن بقيت في الساخن فقط'],
['ماء بني أو صدئ','صدأ في مواسير حديدية قديمة أو في خزان معدني','ليس وحده؛ يلزم فحص المواسير أو بطانة الخزان'],
['قشور بيضاء في الغلاية والأكواب','عسر الماء (أملاح الكالسيوم والمغنيسيوم)','لا؛ هذه ليست علامة خزان متسخ، وحلها فلتر أو مُلطّف للماء'],
['رائحة كلور خفيفة','الكلور المضاف لتعقيم المياه، أو أثر التعقيم بعد الغسيل','غالباً طبيعية وتزول بتشغيل الماء'],
['حبيبات سوداء أو رمل','رواسب في الخزان أو تفتت في الوصلات المطاطية','نفحص الاثنين معاً'],
['ضعف ضغط الماء','انسداد فلتر أو مواسير أو مضخة','غالباً لا، إلا إن كانت الرواسب تسدّ الفلتر'],
])
+TODO('تشخيص أولي من خبرة عامة بالسبّاكة وجودة المياه؛ يراجعه فريق الميدان لديكم، ولا يغني عن الفحص أو التحليل.'))

KB=ksec(H2('كم مرة تنظف خزان المياه؟ ومتى لا تنتظر الموعد؟')
+Pp('لا يوجد رقم واحد يناسب الجميع. نقطة البداية العملية: <strong>مرة كل ستة أشهر كحد أدنى</strong>، ثم تقصّر المدة أو تطيلها بحسب خمسة عوامل تخص خزانك.')
+TBL(['العامل','يقصّر المدة','يطيل المدة'],[
['الشمس والتظليل','سطح مكشوف للشمس طوال اليوم','خزان أرضي مغلق أو مظلّل'],
['مصدر التعبئة','التعبئة بالصهاريج أو من مصدر غير ثابت','تغذية مباشرة من شبكة المياه'],
['الاستخدام','بيت مغلق أو مسافر أهله فترات طويلة (ركود)','استهلاك يومي منتظم يجدد الماء'],
['الغطاء والتهوية','غطاء متضرر أو فتحة تهوية مكشوفة','غطاء محكم وشبك على التهوية'],
['المحيط وعمر الخزان','حفريات قريبة، غبار كثيف، خزان قديم متشقق','خزان حديث بحالة جيدة'],
])
+Pp('وفي صيف الرياض ترتفع حرارة الماء في خزانات الأسطح، فينمو الطحلب والبكتيريا أسرع ويضعف أثر الكلور المتبقي؛ لذلك يُفضَّل أن يكون آخر غسيل قبل دخول الصيف.')
+H3('لا تنتظر الموعد في هذه الحالات')
+UL(['انتقلت إلى بيت لا تعرف متى نُظّف خزانه.','تغيّر لون الماء أو طعمه أو رائحته (راجع الجدول أعلاه أولاً).','أُجريت حفريات أو تمديدات قريبة من الخزان، أو أُصلح الخزان نفسه.','انكسر الغطاء أو انكشفت فتحة التهوية.'])
+TODO('الستة أشهر كحد أدنى قاعدة شائعة؛ يؤكدها فريقكم أو يعدّلها بحسب خبرتكم الميدانية قبل النشر.'))

KDIY=ksec(H2('هل يمكنني تنظيف الخزان بنفسي؟')
+Pp('في حالة واحدة تقريباً: خزان علوي صغير يمكن فتحه وتنظيفه من الخارج دون النزول فيه. أما الخزان الأرضي فلا تنزل فيه أبداً بلا تجهيز؛ فالأماكن المغلقة قد يقل فيها الأكسجين وتتجمع فيها غازات خطرة، كما أن مواد التعقيم قد تنتج غازات سامة إذا اختلطت بمواد أخرى.')
+H3('ما يمكنك فعله بأمان')
+UL(['فحص الغطاء وفتحة التهوية والعوامة من الخارج، وإحكام ما يحتاج إحكاماً.','تظليل الخزان العلوي وتغيير الغطاء الشفاف بآخر معتم.','اختبار الكأس: املأ كأساً شفافاً من الحنفية وانظر إليه أمام الضوء لتلاحظ العكارة أو الجزيئات.','فتح محبس التصريف السفلي، إن وُجد، لتصريف جزء من الرواسب العالقة.'])
+H3('ما لا يُنصح به')
+UL(['النزول إلى خزان أرضي أو مغلق دون تهوية ومعدات سلامة وشخص ثانٍ يراقب من الخارج.','خلط الكلور بأي منظف آخر، خصوصاً الحمضية أو التي تحتوي أمونيا.','التعقيم فوق الرواسب دون إزالتها أولاً؛ فيبقى مصدر التلوث تحتها.','استخدام كلور مركّز دون معرفة تركيزه ومدة تلامسه، أو دون شطف كافٍ بعده.'])
+TODO('نصائح سلامة عامة لا تعتمد على أرقام؛ يضيف فريقكم أي تعليمات سلامة تخص عملكم إن رغبتم.'))

KAFT=ksec(H2('ماذا تفعل في أول 24 ساعة بعد التنظيف؟')
+Pp('بعد إعادة التعبئة، صرّف أول دفعة ماء من المواسير ولا تستغرب رائحة كلور خفيفة؛ هي أثر التعقيم وتزول مع تدفق الماء.')
+UL(['<strong>افتح الحنفيات</strong> في المطبخ والحمامات والدش لعدة دقائق حتى يصفو الماء ويخرج ما ركد في المواسير.','<strong>شغّل الماء الساخن أيضاً</strong> ليتجدد الماء داخل السخان.','<strong>غيّر شمعات فلتر الشرب المنزلي</strong> حسب توصية الشركة المصنّعة؛ فقد تكون التقطت رواسب قبل الغسيل.','<strong>راقب أول يومين:</strong> إن كانت الرائحة في حنفية واحدة فقط، فالمشكلة غالباً في ماسورتها أو خلاطها.','إن استمرت الرائحة قوية في كل الحنفيات أو تغيّر اللون أو الطعم، أوقف الشرب والطبخ بهذا الماء وتواصل معنا لزيارة فحص ضمن الضمان.'],'ol')
+klinks([('أسباب ظهور رائحة كريهة لمياه الخزانات',FAQ+'appearance-of-a-foul-odor-in-the-water-tanks/'),('هل أستخدم المياه فور الانتهاء من التنظيف؟',FAQ+'can-i-use-water-immediately-after-cleaning/')]))

KCAP=ksec(H2('كم سعة خزاني؟ وما المعلومات التي تعطيني سعراً أدق؟')
+Pp('السعة (باللتر) = الحجم بالمتر المكعب × 1000. قِس الأبعاد الداخلية بحسب ارتفاع الماء، لا الأبعاد الخارجية للخزان.')
+UL(['<strong>مستطيل:</strong> الطول × العرض × الارتفاع × 1000.','<strong>أسطواني:</strong> 3.14 × (نصف القطر)² × الارتفاع × 1000.'])
+TBL(['مثال حسابي (للتوضيح)','الحساب','السعة'],[
['خزان أرضي 3 × 2 × 1.5 م','3 × 2 × 1.5 = 9 م³','9,000 لتر'],
['خزان أرضي 4 × 3 × 2 م','4 × 3 × 2 = 24 م³','24,000 لتر'],
['خزان علوي أسطواني، قطره 1.5 م وارتفاعه 2 م','3.14 × 0.75² × 2 ≈ 3.53 م³','نحو 3,500 لتر'],
])
+Pp('هذه أمثلة حسابية للتوضيح وليست مقاسات قياسية. وحتى نعطيك سعراً أدق، أرسل لنا عبر واتساب:')
+UL(['نوع الخزان وموقعه (سطح أم أرضي) وعدده.','السعة التقريبية أو الأبعاد.','متى آخر غسيل، أو أنك لا تعرف.','وجود شروخ أو تسريب أو رائحة.','صورة للغطاء ومحيط الخزان لنعرف سهولة الوصول.'])
+Pp('وما يغيّر السعر عادةً: السعة، ونوع الخزان، وكمية الرواسب، وصعوبة الوصول، وأي إصلاح منفصل. وللمقارنة راجع <a href="#prices">باقات الأسعار</a>.'))

P8x=P[8]+'<p class="wp-block-paragraph">عند الشك اطلب تحليلاً لدى مختبر معتمد، ويشمل عادةً فحصاً ميكروبياً (مثل بكتيريا القولون) وقياس العكارة والكلور المتبقي. وفي الأثناء استخدم مياهاً معبأة للشرب؛ فالغلي يقتل الميكروبات لكنه لا يزيل الرواسب ولا الملوثات الكيميائية.</p>'+klinks([('هل يمكنني استخدام المياه فور الانتهاء من التنظيف؟',FAQ+'can-i-use-water-immediately-after-cleaning/')])+TODO('يُضاف هنا مصدر رسمي لجودة مياه الشرب (جهة مختصة) يحدده فريقكم؛ لا أكتب مصدراً من عندي.')
K4=ksec(P8x)

K5=ksec(H2('كيف أختار شركة تنظيف خزانات؟ جواب جيد وعلامة تحذير')
+Pp('اسأل هذه الأسئلة قبل الحجز؛ فطريقة الإجابة تكشف الشركة أكثر من السعر.')
+TBL(['السؤال','جواب جيد','علامة تحذير'],[
['كيف ستنظفون؟','تفريغ كامل، وإزالة الرواسب، وفرك الجدران، ثم تعقيم ثم شطف','«نعقم بالرش» دون تفريغ'],
['كم السعر؟','يسأل عن النوع والسعة والموقع ثم يعطي سعراً أو نطاقاً واضحاً','سعر ثابت قبل أن يسأل عن شيء'],
['ما مادة التعقيم؟','يسمّيها ويشرح كيف تُشطف','«مادة خاصة» لا يفصح عنها'],
['ماذا سأستلم؟','تقرير بصور قبل وبعد وفاتورة','لا إثبات للعمل'],
['وماذا لو عادت المشكلة؟','ضمان مكتوب بمدة وشروط واضحة','وعد شفهي'],
['هل الإصلاح مشمول؟','يفصل سعر الإصلاح عن التنظيف قبل البدء','مفاجآت في الفاتورة بعد الانتهاء'],
])
+TODO('يُكمَّل بما تقدمه زاد فعلاً، مع رابط لتقرير نموذجي حقيقي من أعمالكم؛ لا أكتب ادعاءات عنكم.'))
PRICES='/cleaning/tanks/prices-riyadh/'
WORK='/works/water-tank-cleaning-al-olaya-riyadh/'
_kall=(
 H2('ما الفرق بين تنظيف الخزان وتعقيمه؟ وهل يكفي أحدهما؟')+review
+Pp('التنظيف عمل ميكانيكي يزيل الطين والرواسب والطحالب، والتعقيم خطوة كيميائية تقلل الميكروبات التي لا تراها. ولا يكفي أحدهما وحده: التعقيم فوق الرواسب لا يصل إلى ما تحتها، فتعود الرائحة خلال أسابيع. لذلك الترتيب الصحيح <strong>تنظيف ثم تعقيم ثم شطف</strong>. وإذا عُرض عليك «تعقيم الخزان» دون تفريغه وفركه، فاسأل: أين ستذهب الرواسب؟')
+H2('كم مرة ننظف الخزان؟')
+Pp('نقطة البداية العملية <strong>مرة كل ستة أشهر كحد أدنى</strong>. وتقصر المدة إذا كان الخزان على سطح مكشوف للشمس، أو يُعبَّأ بالصهاريج، أو يقل استهلاك البيت فيركد الماء؛ وتطول إذا كان أرضياً محكم الغطاء ومتجدد الاستخدام. وفي صيف الرياض ترتفع حرارة ماء الأسطح فتنمو الطحالب أسرع، فاجعل آخر غسيل قبل الصيف. ولا تنتظر الموعد إذا انتقلت إلى بيت لا تعرف تاريخ تنظيفه، أو بعد حفريات قريبة من الخزان، أو إذا تغيّر لون الماء أو طعمه.')
+H2('هل الخزان الأرضي مثل العلوي؟')
+Pp('الهدف واحد والعمل مختلف. العلوي البلاستيكي مشكلته الأكثر الطحالب من الضوء والحرارة، والفيبرجلاس يحتاج مواد غير كاشطة. أما الأرضي فيحتاج وقتاً أطول لأنه مكان مغلق يتطلب تهوية ومعدات دخول آمنة، وقد تظهر في القديم منه شروخ وجذور. وفي العمائر يغذي خزان أرضي واحد عدة وحدات، فنبدأ به ثم بالعلوية، وننسق موعداً يُبلَّغ به السكان، ونسلّم الإدارة تقريراً مصوراً.')
+H2('ماذا يخبرك لون الماء ورائحته؟')
+Pp('الأخضر والطعم الطحلبي علامة طحالب، والرائحة العفنة أو الترابية علامة رواسب في القاع، وكلاهما يعالجه الغسيل. لكن ليست كل مشكلة من الخزان: الماء البني غالباً صدأ مواسير قديمة، ورائحة البيض الفاسد في الماء الساخن فقط غالباً من السخان، والقشور البيضاء في الغلاية سببها عسر الماء ولا يزيلها تنظيف الخزان، أما رائحة الكلور الخفيفة فطبيعية. وإن لم تكن متأكداً فنفحص الخزان والمواسير قبل البدء بدل أن تدفع ثمن غسيل لا يحل مشكلتك.')
+H2('متى يحتاج الخزان صيانة أو عزلاً لا غسيلاً فقط؟')
+Pp('إذا عادت الرواسب أو الرائحة خلال أسابيع رغم غسيل جيد، فالسبب غالباً في الخزان نفسه: شروخ، أو جذور في الأرضي، أو صدأ وتقشر في الطبقة الداخلية، أو غطاء مكسور. نخبرك بذلك قبل البدء ونسعّر الإصلاح أو العزل منفصلاً. وإن عاد اللون الأخضر بعد شهر أو شهرين فالسبب غالباً الضوء أو الحرارة أو ركود الماء، وحله بالتظليل وغطاء معتم محكم لا بتكرار الغسيل.')
+klinks([('أفضل نوع عزل للخزانات الخرسانية',FAQ+'best-type-of-insulation-for-concrete-tanks/'),('هل الخزان الأرضي يحتاج عزلاً؟',FAQ+'does-the-underground-tank-need-insulation/')])
+TODO('مقترح: صفحة مستقلة «عزل خزانات المياه» (880 بحثاً شهرياً وصعوبة 6) تُربط من هنا.')
+H2('ماذا تفعل بعد التنظيف؟')
+Pp('افتح الحنفيات دقائق حتى يصفو الماء، وشغّل الماء الساخن، وغيّر شمعات فلتر الشرب حسب توصية الشركة المصنّعة. رائحة الكلور الخفيفة أثر التعقيم وتزول. فإن بقيت الرائحة في حنفية واحدة فالمشكلة في ماسورتها أو خلاطها، وإن عمّت الحنفيات أو تغيّر اللون فأوقف الشرب وتواصل معنا لزيارة فحص ضمن الضمان.')
+klinks([('هل أستخدم المياه فور الانتهاء من التنظيف؟',FAQ+'can-i-use-water-immediately-after-cleaning/'),('أسباب الرائحة الكريهة',FAQ+'appearance-of-a-foul-odor-in-the-water-tanks/')])
+H2('هل أنظف الخزان بنفسي؟')
+Pp('ممكن في خزان علوي صغير تصل إليه من الخارج. أما الأرضي فلا تنزل فيه بلا تهوية وشخص يراقبك من الخارج، فالأماكن المغلقة قد يقل فيها الأكسجين. ولا تخلط الكلور بأي منظف آخر، فبعض الخلطات تنتج غازات سامة.')
+H2('هل التنظيف يجعل الماء صالحاً للشرب؟')
+Pp(f'يحسّنان جودة الماء لكنهما لا يضمنان وحدهما صلاحيته للشرب؛ فالصلاحية تعتمد أيضاً على المصدر والمواسير والفلاتر وإحكام الغطاء. عند الشك اطلب تحليلاً لدى مختبر معتمد، واشرب مياهاً معبأة في الأثناء؛ فالغلي يقتل الميكروبات لكنه لا يزيل الملوثات الكيميائية. وللمقارنة قبل الحجز راجع <a href="{PRICES}">دليل أسعار تنظيف الخزانات</a>، أو شاهد <a href="{WORK}">نتيجة تنظيف في حي العليا</a>.')
+TODO('يُضاف مصدر رسمي لجودة مياه الشرب (جهة مختصة) يحدده فريقكم.')
+H2('كيف أختار شركة تنظيف خزانات؟')
+Pp('اسأل: هل ستفرّغون الخزان وتزيلون الرواسب قبل التعقيم؟ ما مادة التعقيم وكيف تُشطف؟ هل أستلم تقريراً مصوراً قبل وبعد؟ هل الضمان مكتوب بمدة وشروط؟ وهل سعر الإصلاح منفصل عن التنظيف؟ واحذر من يعطيك سعراً قبل أن يسأل عن نوع الخزان وسعته، أو يكتفي بالرش دون تفريغ. وللسعر الأدق أرسل لنا نوع الخزان وسعته التقريبية (الطول × العرض × الارتفاع بالمتر × 1000 = لتر) وصورة للغطاء.')
)
KALL=ksec(_kall)
import re as _re
_txt=_re.sub(r'<p class="mk__todo">.*?</p>','',_kall); _txt=_re.sub(r'<[^>]+>',' ',_txt)
print('KNOWLEDGE WORDS',len(_re.findall(r'[ء-ي٠-٩A-Za-z0-9]+',_txt)))

K6=ksec('''<h2 class="wp-block-heading">كيف أحجز وكيف أدفع؟</h2>
<ol class="wp-block-list"><li>تواصل معنا عبر واتساب أو الاتصال، وأخبرنا بنوع الخزان وحيّك.</li><li>نحدد موعداً في نفس اليوم أو اليوم التالي.</li><li>ننفذ الزيارة ونسلّمك تقريراً مصوراً.</li></ol>
<p class="mk__todo">⟵ سطر طرق الدفع يُقرأ من الإعدادات (غير مكتوب في الموقع الآن، فلا يظهر حتى يُدخَل).</p>''','book')
# ---------- video (optional) ----------
video='''<section class="sec sec--dark" id="video"><div class="wrap wrap--narrow"><header class="sec__head"><span class="eyebrow">شاهد الفرق بنفسك</span><h2>شاهد كيف نغسل الخزان</h2></header>
<div class="vframe" style="--poster:linear-gradient(135deg,#0a3947,#0c687e)"><button type="button" class="vframe__play" aria-label="تشغيل الفيديو"><svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28"><path d="M8 5v14l11-7Z"/></svg></button></div></div></section>'''
# ---------- business data + map ----------
biz='''<section class="sec sec--tint" id="biz"><div class="wrap">
<header class="sec__head"><span class="eyebrow">من نحن وأين نخدم</span><h2>مناطق خدمة تنظيف الخزانات وبيانات الشركة</h2></header>
<div class="biz"><div class="biz__card"><ul class="biz__list">
<li><b>الشركة</b><span>شركة زاد السعودية للصيانة والنظافة</span></li>
<li><b>نطاق الخدمة</b><span>الرياض بجميع أحيائها</span></li>
<li><b>العنوان</b><span>2851 شارع عبدالملك بن مروان، حي العليا، الرياض 12611</span></li>
<li><b>الهاتف</b><span dir="ltr"><a href="tel:0552744437">0552744437</a></span></li>
<li><b>ساعات العمل</b><span>من 8 صباحاً إلى 11 مساءً طوال أيام الأسبوع</span></li>
<li><b>السجل التجاري</b><span>1010769699</span></li></ul></div>
<div class="biz__map"><div class="biz__pin">'''+'''<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>'''+'''</div><a class="btn btn--accent" href="https://www.google.com/maps?q=24.774265,46.738586" target="_blank" rel="noopener">عرض الموقع على خرائط جوجل</a><small>تُحمَّل الخريطة عند الضغط فقط (لا تؤثر على سرعة الصفحة)</small></div></div></div></section>'''
# ---------- works + gallery + expert + reviews ----------
arts=re.findall(r'<article class="scard scard--post zw-card zw-card--pro">.*?</article>',works,re.S)
first=arts[0]; w2=works.replace(arts[1],'') if len(arts)>1 else works
w2=w2.replace('data-n="2"','data-n="1"')
gal=re.search(r'<div class="gal">.*?</div>',gallery,re.S).group(0)
w2=re.sub(r'</div></section>\s*$','<div class="need__grp"><header class="sec__head"><h3>صور من زيارات تنظيف خزانات بالرياض</h3></header>'+gal+'</div></div></section>',w2.rstrip(),count=1,flags=re.S)
expert='''<section class="sec"><div class="wrap wrap--narrow"><div class="expert">
<span class="expert__av" aria-hidden="true">م</span><div><small>راجع المحتوى الفني</small><h3><a href="http://zadksa.local/author/mahmoud/">محمود القحطاني</a></h3><p>محرر المحتوى الفني في زاد السعودية. يعتمد في إعداد المحتوى على زيارات مواقع التنفيذ، وملاحظات فرق العمل، وأسئلة العملاء الفعلية.</p></div></div></div></section>'''
reviews_slot='<div class="mk__empty"><b>مراجعات عملاء حقيقية</b><br>تظهر هنا فقط عند إدخال مراجعات فعلية (الاسم + الحي + التاريخ + نص المراجعة) مع رابط «كل المراجعات على خرائط جوجل». لا يوجد شيء يُعرض للزائر الآن.</div>'
# ---------- FAQ merged ----------
qn=re.search(r'<div class="qnet">.*?</div>',qnet,re.S).group(0); qall=re.search(r'<p class="qnet__all">.*?</p>',qnet,re.S).group(0)
DROP=['كم سعر تنظيف الخزانات بالرياض','كم مرة يجب تنظيف خزان المياه','هل تنظفون خزانات العمائر والمنشآت','هل تصلحون شروخ الخزان وتسريبه']
def _drop(m):
    return '' if any(d in m.group(0) for d in DROP) else m.group(0)
faq=re.sub(r'<details class="faq__item">.*?</details>',_drop,faq,flags=re.S)
faq2=re.sub(r'</div></section>\s*$','<div class="need__grp"><header class="sec__head"><h3>أسئلة تفصيلية عن تنظيف الخزانات</h3></header>'+qn+qall+'</div></div></section>',faq.rstrip(),count=1,flags=re.S)
# ---------- related ----------
def card(u,t,d): return f'<article class="scard scard--lite"><a class="scard__lite" href="{u}"><h3 class="scard__title">{t}</h3></a><p class="scard__desc">{d}</p></article>'
rel='<section class="sec sec--mint"><div class="wrap"><header class="sec__head"><span class="eyebrow">خدمات أخرى</span><h2>خدمات أخرى قد تحتاجها</h2></header><div class="sgrid">'+''.join([
 card('http://zadksa.local/cleaning/tanks/jeddah/','تنظيف خزانات بجدة','نفس الخدمة في مدينة أخرى'),
 card('http://zadksa.local/cleaning/air-conditioning/riyadh/','تنظيف مكيفات بالرياض','غسيل المكيفات بعد الخزانات في نفس الزيارة'),
 card('http://zadksa.local/cleaning/sofa/riyadh/','تنظيف كنب بالرياض','للبيت نفسه في نفس الأسبوع')])+'</div></div></section>'
# ---------- compose ----------
_n=[0]
CIRC='①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮⑯⑰⑱⑲⑳'
def sec(kind,label,x,cls=''):
    i=_n[0]; _n[0]+=1
    return wrap(kind,(CIRC[i] if i<len(CIRC) else str(i+1))+' '+label,x,cls)
order=[
 sec('keep','الهيرو — كما هو (بلا سطر سعر إضافي: بطاقة الحجز فيه تعرض «يبدأ من»)',hero),
 sec('merged','هل يحتاج خزانك تنظيفاً الآن؟ — أول محتوى تحت الهيرو (الاختبار مطوي بزر + العلامات)',needs),
 sec('moved','ماذا يحدث إذا أجّلت التنظيف؟ — تحت قسم الحاجة مباشرة (كان 13 من 18)',harms),
 sec('moved','الأسعار — منقولة لفوق (كانت 11 من 18) + سطر المنشآت + آخر تحديث',price),
 sec('merged','الخطوات — منقولة لفوق + «الأداة» لكل خطوة بدل قسم المعدات',steps),
 sec('new','فيديو — يظهر فقط عند وجود رابط (لا يوجد رابط في هذه الصفحة الآن)',video),
 sec('moved','من أين يبدأ تلوث الخزان؟ — انتقل من قسم الحاجة إلى قسم المعرفة',mp),
 sec('merged','قسم المعرفة — 9 أسئلة بلا جداول، نحو 620 كلمة (يحلّ محل 6 أقسام سابقة)',KALL),
 sec('keep','أحياء الرياض (روابط نصية) — بعد حذف مربع «30 يوماً ضمان» المكرر منها',cov),
 sec('new','بيانات الشركة + الخريطة (بيانات حقيقية من موقعك) — الخريطة تُحمَّل عند الضغط',biz),
 sec('merged','أعمالنا + صور الزيارات في قسم واحد (واستُبعد عمل «كنب وموكيت»)',w2),
 sec('new','بطاقة المشرف (من حساب الكاتب الحقيقي)',expert),
 sec('new','المراجعات الحقيقية — لا تظهر إلا ببيانات',f'<section class="sec"><div class="wrap">{reviews_slot}</div></section>','mk--ghost'),
 sec('merged','الأسئلة الشائعة — 4 غير مكررة + 6 تفصيلية = 10 في قسم واحد',faq2),
 sec('new','خدمات أخرى قد تحتاجها',rel),
]
removed='''<div class="mk-removed"><h3>اللي اتشال من الصفحة (مكرر)</h3><ul>
<li><b>المقدمة</b> («شركة تنظيف خزانات بالرياض من زاد…») — كررت الأسعار الثلاثة والضمان وخطوات الزيارة الست حرفياً تقريباً (والسعر نفسه في سؤال الأسئلة). رابط القسم الأب (تنظيف الخزانات) موجود في مسار التنقل.</li>
<li><b>شريط الأرقام</b> (150 / 250 / 30 يوم / مرتان) — الأربعة مكررة في الهيرو والأسعار والأسئلة.</li>
<li><b>«أنواع تنظيف الخزانات»</b> — خمس بطاقات هي نفس باقات الأسعار الخمس؛ المنفرد فيها (المنشآت) انتقل لسطر تحت الأسعار.</li>
<li><b>قسم المعدات</b> — عنوانه ثابت في كل الخدمات؛ معلوماته في سطر «الأداة» داخل الخطوات.</li>
<li><b>مربع «30 يوماً ضمان مكتوب»</b> داخل قسم الأحياء — الضمان مذكور في الهيرو والباقات وسؤال الضمان.</li>
<li><b>«صور من زيارات» و«أسئلة تفصيلية» كأقسام مستقلة</b> — اندمجا في «أعمالنا» وفي الأسئلة.</li>
<li><b>أسئلة مكررة:</b> «كم سعر» (قسم الأسعار)، «كم مرة» (قسم كم مرة ننظف)، «خزانات العمائر والمنشآت» (قسم الأرضي والعلوي وسطر المنشآت)، «تصلحون شروخ» (قسم الصيانة والعزل وباقة العزل).</li>
<li><b>عمل «كنب وموكيت»</b> من قسم أعمال الخزانات — ليس عن الخزانات.</li></ul>
<p>لا يتغير: الهيدر والفوتر والشريط السفلي. لا CTA أخير (قرارك في 4.0.9).</p></div>'''
summary='''<div class="mk-top" dir="rtl"><div class="mk-top__bar"><b>نموذج تصميم — لم يُطبَّق على الموقع</b><span>صفحة /cleaning/tanks/riyadh/ بمحتواها الحقيقي</span>
<button type="button" id="mk-toggle">إخفاء الملاحظات</button></div>
<div class="mk-top__legend"><i class="mk__tag mk__tag--keep">باقٍ كما هو</i><i class="mk__tag mk__tag--moved">منقول</i><i class="mk__tag mk__tag--merged">مدمج</i><i class="mk__tag mk__tag--new">جديد</i></div></div>'''
mk_css='''
.mk{position:relative}
.mk__tag{display:block;position:relative;z-index:30;font:700 13px/1.5 Tajawal,sans-serif;padding:6px 16px;color:#fff}
.mk-top__legend .mk__tag{display:inline-block;margin-inline-end:8px;border-radius:999px;padding:3px 12px}
.mk__tag--keep{background:#5b6b73}.mk__tag--moved{background:#0c687e}.mk__tag--merged{background:#7a3fb4}.mk__tag--new{background:#1b8a4b}
.mk-hide .mk__tag,.mk-hide .mk__todo,.mk-hide .mk-removed{display:none}
.mk-top{position:sticky;top:0;z-index:99999;background:#0f172a;color:#fff;font:600 14px Tajawal,sans-serif}
.mk-top__bar{display:flex;gap:14px;align-items:center;flex-wrap:wrap;padding:10px 16px}.mk-top__bar span{opacity:.8;font-weight:500}
.mk-top__bar button{margin-inline-start:auto;border:0;border-radius:999px;padding:6px 14px;font:700 13px Tajawal;background:#f49400;color:#1a1200;cursor:pointer}
.mk-top__legend{padding:0 16px 10px}
.mk__empty{border:2px dashed #9aa7ad;border-radius:12px;padding:18px;text-align:center;color:#51616a;background:rgba(255,255,255,.6)}
.klinks{font-size:.95rem;margin:.6rem 0 0;color:#51616a}.klinks a{font-weight:700}.kreview{font-size:.85rem;color:#51616a;border-inline-start:3px solid var(--accent,#d4a24c);padding-inline-start:10px;margin:0 0 1rem}
.mk__todo{border:2px dashed #9aa7ad;border-radius:10px;padding:8px 12px;color:#51616a;font-size:.9rem}
.mk--ghost .mk__tag{background:#1b8a4b}
.mk-removed{max-width:1000px;margin:30px auto;padding:20px 24px;border:2px dashed #b32d2e;border-radius:14px;background:#fff7f7;color:#222}
.mk-removed h3{margin:0 0 10px;color:#b32d2e}.mk-removed li{margin:6px 0;line-height:1.8}
.hdr{position:relative!important}
'''
new_blocks_css='''
/* === CSS المقترح لإضافته للثيم (للكتل الجديدة فقط، بنفس متغيرات الألوان) === */
.shero__from{display:inline-flex;flex-wrap:wrap;align-items:baseline;gap:6px 10px;margin:16px 0 0;padding:8px 16px;border-radius:999px;background:rgba(255,255,255,.14);color:#fff;font-weight:700}
.shero__from b{font-size:1.35rem;color:var(--accent)}
.pkgx__upd{margin:10px 0 0;text-align:center;color:var(--muted);font-size:.9rem}
.hsteps__tool{margin:10px 0 0;padding:8px 12px;border-radius:10px;background:rgba(12,104,126,.08);font-size:.9rem;line-height:1.7}
.need__details{margin:26px 0 0}.need__details>summary{cursor:pointer;list-style:none;display:flex;width:100%;max-width:640px;margin-inline:auto;justify-content:center}.need__details>summary::-webkit-details-marker{display:none}.need__details[open]>summary{margin-bottom:10px}
.need__grp{margin-top:34px}.need__grp>.sec__head h3,.need__grp>header h3{font-size:1.25rem;margin:0 0 12px}
.need__grp.ix{padding:0}.need__grp .wrap{padding-inline:0}
.biz{display:grid;gap:20px;grid-template-columns:1fr}
.biz__card,.biz__map{background:var(--surface,#fff);border:1px solid var(--line,#dfe6e9);border-radius:var(--r,14px);padding:22px}
.biz__list{list-style:none;margin:0;padding:0;display:grid;gap:14px}.biz__list li{display:grid;gap:2px}.biz__list b{color:var(--muted);font-size:.85rem}.biz__list span{font-weight:700}
.biz__map{display:grid;place-items:center;align-content:center;gap:12px;min-height:260px;text-align:center;background:repeating-linear-gradient(45deg,rgba(12,104,126,.05) 0 12px,rgba(12,104,126,.09) 12px 24px)}
.biz__pin{color:var(--primary)}.biz__map small{color:var(--muted)}
.expert{display:flex;gap:16px;align-items:center;background:var(--surface,#fff);border:1px solid var(--line,#dfe6e9);border-radius:var(--r,14px);padding:18px 20px}
.expert__av{flex:none;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;background:var(--primary);color:#fff;font-weight:800;font-size:1.4rem}
.expert small{color:var(--muted)}.expert h3{margin:2px 0 6px;font-size:1.1rem}.expert p{margin:0;color:var(--muted);line-height:1.8;font-size:.95rem}
@media(min-width:860px){.biz{grid-template-columns:1.1fr .9fr}}
.zw-grid4:has(> :only-child){grid-template-columns:minmax(0,420px);justify-content:center}
'''
SPRITE=re.search(r'<svg[^>]*width="0"[^>]*>.*?</svg>',h,re.S).group(0)
pre_all=h[h.find('<body'):h.find('<main')]
hdr=re.search(r'<a class="skip".*?</header>',pre_all,re.S).group(0)
suf=h[h.rfind('</main>')+7:]
ftr=suf[:suf.find('</footer>')+9]
dock_i=suf.find('<div class="dock"'); dock=suf[dock_i:suf.find('</div>',dock_i)+6]
hdr=unlazy(hdr); ftr=unlazy(ftr)
doc=f'''<!DOCTYPE html>
<html dir="rtl" lang="ar"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>نموذج تصميم صفحة الخدمة — تنظيف خزانات بالرياض</title>
<style>{fonts_css}</style><style id="zad-vars">{vars_css}</style><style id="zad-theme-css">{css_theme}</style>
<style id="mk-new-blocks">{new_blocks_css}</style><style id="mk-chrome">{mk_css}</style></head>
<body class="rtl single single-cleaning zad" id="mk-body">{SPRITE}{summary}{hdr}<main id="main">{''.join(order)}{removed}</main>{ftr}{dock}
<script>{rd(ROOT+'/assets/js/zad-ix.js')}</script><script>document.getElementById('mk-toggle').addEventListener('click',function(){{var b=document.getElementById('mk-body');b.classList.toggle('mk-hide');this.textContent=b.classList.contains('mk-hide')?'إظهار الملاحظات':'إخفاء الملاحظات';}});
</script></body></html>'''
open(OUT,'w',encoding='utf-8').write(doc)
print('written',OUT,len(doc)//1024,'KB')
# stats
print('H2 count in main:',len(re.findall(r'<h2',''.join(order))),' words:',wc(''.join(order)))
