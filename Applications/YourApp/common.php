<?php

// 应用公共文件


function create_group_list(): array
{
    $group_list=[];
    for ($i = 1; $i <= 100; $i++) {
        $group_list[]=[
            'id'=>$i,
            'name'=>getNickName(),
            'avatar'=>'group-avatar/'.mt_rand(1, 234).'.jpg',
            'type'=>2,
        ];
    }
    return $group_list;
}

/**
 * 获取ack序列号
 * @param string|int $tag
 * @return int
 */
function get_ack_sn(string|int $tag): int
{
    try {
        global $global_data;
        $key='get_ack_sn_'.$tag;
        if(!isset($global_data->$key)) $global_data->$key=1;
        if($global_data->$key >= 999999999){
            $global_data->cas($key, $global_data->$key, 1);
        }
        $global_data->increment($key);
        return $global_data->$key;
    } catch (Throwable $e) {
        write_log('获取ack序列号：异常==》'.$e->getLine().$e->getMessage(),'错误');
        return 1;
    }
}

/*
 * 发出消息
 * @return string
*/
function emit_msg(string $event_name, ...$msg): string
{
    $msg_arr=[$event_name,...$msg];
    return '42'.json_encode_cn($msg_arr);
}

/*
 * 发出消息回复(ack)
 * @return string
*/
function emit_msg_ack_res(string|int $ack, ...$msg): string
{
    $msg_arr=[...$msg];
    return '43'.$ack.json_encode_cn($msg_arr);
}

/*
 * 发出消息(ack)
 * @return string
*/
function emit_msg_ack(string $event_name,$fn_name, ...$msg): string
{
    $sid=$_SESSION['sid'];
    $ack_sn=get_ack_sn($sid);
    $_SESSION['ack_'.$ack_sn]=$fn_name;
    $msg_arr=[$event_name,...$msg];
    return '42'.$ack_sn.json_encode_cn($msg_arr);
}

/*
 * 获取日期
 * @return string
*/
function get_date($type=0): string
{
    $date = new DateTime();
    return match ($type) {
        1 => $date->format('Y-m-d H:i:s.u'),
        2 => '[' . $date->format('Y-m-d H:i:s.u') . '] ',
        default => $date->format('Y-m-d H:i:s'),
    };
}

/**
 * 写入日记
 * @param string|int $logMessage
 * @param string $logType
 * @param string $logRootDir
 * @return void
 */
function write_log(string|int $logMessage,string $logType='信息',string $logRootDir='log'): void
{
    try {
        $current_datetime=get_date(1);
        $currentDate = date("Y-m-d");
        $logContent = '['.$current_datetime . ']: '. $logMessage;
        if(env('APP_DEBUG')){
            echo match ($logType) {
                '警告' => "\033[33m" . $logContent . "\033[0m\n",
                '错误' => "\033[31m" . $logContent . "\033[0m\n",
                default => "\033[36m" . $logContent . "\033[0m\n",
            };
        }
        $logContent .= "\n";
        $logDir = $logRootDir . "/" . date("Y-m");
        $logFile = $logDir . "/"  . $currentDate. "_". $logType  . ".log";

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        if (file_put_contents($logFile, $logContent, FILE_APPEND) === false) {
            throw new Exception("日志写入失败");
        }
    } catch (Exception $e) {
        echo "日志写入Error: " . $e->getMessage();
    }
}

/**
 * json编码，中文处理，json_encode
 * @param $data
 * @return bool|string
 */
function json_encode_cn($data): bool|string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}


function getNickName(): string
{
    $str = "灭红神##天幽狼##血擎刀##逼摇妹er，##ar丶将军灬##月聊无痕##缕寒流##利铜锣烧##yond丨极限##寒夜々孤星##血战苍穹##丿星座灬天堂彡##淩亂玫瑰##|丶利刃メ出鞘丶##轩哥∥逆天改命##萧萧暮雨##烈火战神##橘萝卜蹲@##血虐修罗##歲月无痕##影猎杀戮##骑刀锋寒##宠物女孩##寒玉簪秋水##农场小镇##探险家##☆夏°指閒緑═→##叼着根香蕉闯遍天下##耀世红颜[由Www.QunZou.Com整理]##蓝铯の峢痕##现代战争##僵尸勿近##死怎样舒服##灭零##自嗨怪@##墨染迷舞##清歌留欢##天使★翅膀╮##幼稚女神蹦蹦恰！##南柯无痕##金鱼嘴@##战龙三国##ざ碎情漂移ド##清尊素影##梦幻舞步##三朝元老i##称雄三界##雨后の微笑##念荒寒##提莫队长正在送命##无心者##一阵寒风##〆﹎兲使雪↘##戎马↘生涯﹌##哈哈神###微微寒风秋凄凉##顽皮灬贝贝##风流雅痞妖姑娘っ##q水浒##萌面大盗##霸刀☆藐视天下##玩飞车滴孩子我陪你##不懂我心寒##夜店之王##顾北清歌寒##玉衡逍龙##萌尊！##红眼★夜袭彡##火花★之炫##煜逅彩泓##闹爷@##买梦人i##大侠传##从筠##坦克##南山不寒##蹦蹦蹦V@##omair圣珂##霸天修罗##愤怒的小鸟##冰残°零度伤~##滑雪大冒险##影丿战魂##野狼@##萨普莱斯##摩尔庄园##南梦破碎##赏你奔雷掌i##ゴ二逼主义°##龙剑##妥神！##逆战##草率怪~##枪心。@##っ惊恐嘟##北巷远人##清酒与你##鬼寐﹏孤寂##该用户身份不明 ##绿竹猗猗 ##潑墨為畫 ##秋叶满地伤 ##江山寒色远 ##紫醉琴缘 ##预感大师 ##楓葉晓寒 ##傲视判决 ##初夏少女 ##黄萝卜诅咒@ ##千囚迷森 ##笙歌初寒夜未央 ##洫聙↘刀锋 ##独步死神 ##◇ヽ少爷╰★╮ミ逍遙営 ##℃梦醒 ##深葬长白雪 ##叫兽光波i ##水果连连看 ##戎马↘追风 ##￡极限★雨寒彡 ##凡人修真 ##部落战争 ##葫芦娃 ##贫僧灬不虐人 ##ヤpk淪為笑柄 ##南岸清风 ##心浪微勃 ##拖拉机小游戏 ##双月之夜_KRIS控 ##尛爺半生沉浮゛° ##格格巫i ##王者天下 ##新仙剑 ##北木在北 ##破恨南飞 ##战魔の不灭 ##打豆豆 ##咆哮战歌 ##皇后成长计划 ##亡梦爱人 ##皇族鬼圣 ##河南车神灬 ##俄罗斯方块 ##ヤGm﹎鬼魅℡ ##ぁ血刹风云城ぁ ##℡嵿级╔舞灵族╗ ##捕鱼达人 ##嗜魂龙吟 ##狂扁小朋友 ##╬最终メ狂暴╬ ##拿把雨伞装蘑菇 ##战神将军@ ##飞天女侠啵啵啵@ ##愤怒的小鸟太空版 ##神堂丨灬艹鸳鸯战 ##one丶piece丿r ##ㄗs風流』締國︶ㄣ ##逆水寒 ##独孤久贱 ##一滴水墨 ##mm小游戏 ##o戰☆無情★ ##┾恋戦℃ャ逍遥 ##oO冰暴★战神Oo ##掩卷别寒窗 ##贪婪虫 ##短发郁夏天 ##暴力摩托 ##帝王魂 ##扒一扒。 ##sky★血狼 ##哀而不伤 ##我请寒假吃炫迈 ##梦里花易寒 ##黑影子^ ##撕书狂魔i ##找你妹 ##久伴不弃 ##狼少° ##街头霸王 ##止步ツ梦江南 ##绝代风骚 ##￡袅袅★烟云彡 ##寒春玉柳 ##萌哒女屌^ ##凯撒大帝！ ##土鳖中的战斗鳖 ##妖美人@ ##所叹浅夏夜更寒。 ##新人不好混啊！ ##果蔬连连看 ##[亡心者] ##哑巴汤姆猫@ ##星战 ##浴血み神鷹 ##荳蔻年華 ##修罗现世 ##对对碰 ##-大爆炸 ##一盏孤灯 ##『地狱★男爵』 ##极限丶雨寒 ##北海茫月 ##黄金矿工双人版 ##符咒Thedevil◢ ##彡炫灬月影萧梦灬 ##回首寒暄 ##直升机模拟飞行 ##丿star炫love绿茶 ##画轴初寒 ##舞蹈黑钟 ##苍空的蓝耀 ##≮神秘人≯ ##◇嗼尛寒◆ ##烽火ヤ角逐 ##青山灼灼 ##<诡使> ##Regin ##弑魂无情 ##旅人与风 ##萌面女神 ##栀寒老酒 ##づ暗暗的伤╄ ##神庙逃亡 ##Promise°魅眸 ##寒烟冷°浅暮流殇 ##←lěι濕枕寒→ ##猫溺怀歌者’ ##sun╮寒霜 ##风云之王 ##抿口老酒 ##似冷非寒冰i ##残月絮辰 ##慕雨遙长 ##雨落伊人 ##挽吟袖 ##天空不空 ##死亡公路 ##南风未恋 ##泪湿枕寒 ##大闹天宫ol ##圣诞节的节奏~ ##黑狱☆擎天 ##冒险小镇 ##血战メ傲神 ##ＳＣメ戰無不勝 ##回忆づ我 ##龙刀杀戮 ##笙歌初寒夜未央 ##飒舛流寒i ##神将世界 ##硝烟冉冉 ##萌兽 ##颠峰霸天 ##灭世メ狂刀 ##q宠大乐斗 ##魁首阿^ ##ら魔粉の晓涛 ##疯狗三人组 ##领嗨掌门 ##血战メ孤狼 ##傲视天地 ##姐统领守护 ##泡沫女神i ##带我装逼带我飞# ##诗的颈窝 ##丿mx传说艹灬急速 ##不计寒暑 ##糖果味的初夏 ##乱世魔枭 ##丶绝望的战斗机i ##UnderTaker（送葬者） ##慕心倾寒 ##梦遗少年 ##别逃呀! ##战无不胜 ##浮动的记忆り ##在寒星中苏醒 ##女爆君@ ##坦克之怒 ##眼泪是魔鬼 ##〆霸气开爷° ##兔子大爆炸 ##无念倾颓 ##灭世狂舞 ##妖孽少年 ##づ`啵钣赯﹏ ##杀戮为生 ##枪魂 ##倾国妖魔ぃ ##哆啦A梦修理工场 ##^上↗璇月℡ ##烟雨寒~伊人醉 ##柚子半夏 ##醉心眠 ##神魔领域 ##横尸遍野 ##女战将i ##斗龙战士 ##北城凉筑 ##美食大战老鼠 ##羽翼之魔 ##噬血魅影@ ##·turbine°（涡轮） ##◆幻じ灭つ ##黄金矿工 ##百变五瞎i ##今夜酷寒不宜裸奔 ##乱世小熊 ##攻城略地 ##空白小姐 ##魔境仙踪 ##北①丿战魂 ##始终与你 ##幕_无情花舞 ##北有凉城 ##滚吧水果 ##Explosion°[爆炸] ##地铁跑酷 ##一发绝杀 ##稳中求胜，@ ##◢◢无名★悍将◣◣ ##神妖@ ##保卫萝卜 ##苍凉满目 ##寒岁饮醉 ##梅寒风独开 ##水却要煮鱼 ##细雨轻愁 ##少年纪@ ##坦克世界 ##大鱼吃小鱼 ##月狼の啸天 ##将军令 ##北栀怨寒 ##≮Azrael★柒岁≯ ##欢乐斗地主 ##清风清梦 ##长空夕醉 ##秦美人 ##霸气傲世 ##凉宸 ##弑神者 ##￡冰泣↘美人鱼彡 ##丿莫兮丶风流寨丨 ##修真世界 ##高尔夫射击 ##心岛未晴 ##傲寒 ##厕所有枪声 ##紫色百合 ##乱斗堂 ##光治愈独角兽罒?罒 ##Top丶彪悍丿 ##江东过客 ##极品台球 ##阿sue做蛋糕 ##嗜血メ狂虐 ##月冷花残♂ ##如来神掌 ##°籽萝卜蹲@ ##主治撩妹 ##大周皇族 ##冷心寒心 ##繁華的世間 ##寒武紀的月光 ##天界 ##亮瞎你的眼 ##╰ゞ姐独占天下℡ ##▲°喧哗夜黎-Miss ##Govern(统治) ##宿妖瞳|CATOBLEPAS- ##一世霸主 ##-彻骨寒 ##衾枕寒 ##南影倾寒 ##飞无痕廴两院 ##泡泡堂 ##放假者们@ ##∑gray°鱼ル ##HeartAttack ##借风凉心 ##光影神力 ##圝◣帝潮◢圝 ##反恐精英 ##变形金刚 ##情若幽兰 ##||丶问鼎★青龙丶 ##死阿宅@ ##水果忍者 ##塞外う飛龍 ##邪恶% ##FlappyBird ##黑巷少年 ##吞食天地 ##皇帝成长计划 ##逆光夏花 ##僚机中的战斗机@ ##噬魂こfeel ##@女叫兽 ##北风承欢 ##寂念流年 ##识透人情寒透心 ##黄萝卜魔咒 ##傲视＆狂朝 ##和风戏雨 ##五子棋 ##寒烟冷浅暮殇 ##那年夏天的歌 ##木屋茶花 ##生死悠关组合！ ##会说话的汤姆猫 ##※豪〓城〓世〓家※ ##卡通农场 ##深巷古猫 ##脑溢血的驴 ##丨极速灬巅峰彡丨 ##￡剑魂メ秒杀彡 ##污界小公举 ##自嗨宝@ ##亦情，凡恋@ ##大将军 ##青涩碧瞳 ##轰炸女王 ##陌上↘寒迁 ##夜ゞ月影 ##英雄脸萌 ##￡忧殇☆寒影╮ ##拳皇 ##橙子军团！ ##寒兮唸红颜° ##大富翁 ##泡泡龙 ##夕陽西下 ##音桀@ ##热血三国 ##我是VIP呀! ##BlackKnight- ##破军╃升龙 ##萌心初动 ##南瓜婆婆 ##孤影月下 ##梵音与笙 ##霸气无双 ##大家来找茬 ##一世倾城 ##魅影丨时尚灬 ##逐风★黑翼 ##山河风光 ##诛心人i ##翠烟寒 ##≮绿蕊★紫蓝≯ ##大队长 ##黑骑士@ ##超级玛丽 ##暴君与猫@ ##妄断弥空 ##姑你二大爷@ ##光辉终结 ##丠颩箛唥 ##蓝戈者 ##疯狂的玩具 ##南熙寒笙 ##无极剑圣i ##||丶王者★无双丶 ##爆菊神话 ##Dancer丿（舞者） ##冷月寒笙 ##锤伢破天 ##伴我几度 ##宠物连连看 ##轩辕譹冂oоο ##北海未暖 ##南烟在南 ##你画我猜 ##终极战犯 ##南巷近海 ##≮盘古★帝神≯ ##丿石门灬独圣 ##ご盗贼耍↘蛋刀﹌ ##短巷闻雨 ##七雄争霸 ##街舞少年i ##蒙面超女 ##甜味的风 ##南岸初晴 ##≮盖世★袅雄≯ ##九月的雪 ##vip紫炫灬风舞 ##骑猪兜风 ##南鱼在流浪 ##冬雾寒凉 ##子夜寒风 ##≮佳乐★宝贝≯ ##当初的我 ##复习者联盟@ ##花落↘冰层 ##肃杀’ ##长夜有星光 ##至尊D叉车 ##蝙蝠不会飞 ##鹿霸@ ##拖沓囍天王 ##火麒麟 ##死了心的心 ##寻觅海洋的鱼 ##潇潇暮雨 ##**女神SIBIAO ##凄寒半世留殇铭 ##斗破苍穹 ##死亡镰刀 ##伟大旳苍穹 ##怪咖软妹@ ##灰机灰过去了 ##楠木青城 ##将神 ##十年寒如雪丶 ##明星志愿之好莱坞 ##凝泪入侵者·@ ##惊悚怪 ##剪剪清风 ##devil★战神 ##三国战记 ##南栀倾寒° ##血葬③界 ##蔑王侯 ##暖风与她 ##植物大战僵尸 ##一笑震九天 ##完美风暴 ##魂斗罗 ##卿弦季鸢 ##凡尘清心 ##龙洁寒 ##||丶至尊★君绝﹌ ##突击风暴 ##我亦无泪纵横i ##ご噬魂★魅影﹌ ##扎小辫的帅锅 ##凉生初雨 ##幻夜冰羽 ##萝卜蹲i ##灭世炙天 ##魅色夫人 ##蜘蛛纸牌 ##彼岸髅灵 ##公子泺尘 ##寒梅↘傲立 ##≮飘飘★伊人≯ ##寒光竹影ぃ ##狂人领袖i ##傲剑 ##ご屌炸天的节奏ヾ ##大脸怪@ ##枪神 ##风云ㄋ战焰 ##ヤ未央мo寒メ ##眨眼的舒马赫 ##小黄人快跑 ##杀戮灭人性 ##狼门★血影 ##孤冥 ##聚会玩 ##覆旧人寒 ##￡魔龙★祭天彡 ##Roar&咆哮 ##喂鱼抽猫- ##嫌淡超人i ##乱流年颜目 ##柔媚~妖瞳 ##微胖女神 ##旋风小陀螺 ##秋风追猎者 ##￡浅笑↘嫣然彡 ##醉里秋波 ##持枪瞄准BOOM! ##死神★镰刀 ##@暖宝宝妖精 ##海浔深蓝 ##樱舞灬默寒 ##迷你大城市 ##虐杀原形 ##死亡MUSIC ##寒峰叶落 ##渚寒烟淡 ##念寒冬里的夜空 ##搞怪碰碰球在线玩 ##七月纪旅 ##芭比萌少女^! ##毒魂SHOOT， ##密室逃脱 ##报之以李 ##‰统帅→ ##丿安萧灬若痕丨轩 ##半裸时代fell ##☆刀霸※离伤☆ ##Provenceつ暗眸° ##寒冰★冠世 ##瞬灭天下 ##月下☆清影";
    $arr = explode("##",$str);
    $r = rand(0,sizeof($arr)-1);
    return $arr[$r];
}

