/**
 @ Name：layui.regionCheckBox 中国省市复选框
 @ Author：wanmianji
 */

layui.define('form', function(exports){
	
	var $ = layui.$
	,form = layui.form
	,MOD_NAME = 'regionCheckBox', ELEM = '.layui-regionContent'
	,regionCheckBox = {
		index: layui.regionCheckBox ? (layui.regionCheckBox.index + 10000) : 0
		
		,set: function(options){
			var that = this;
			that.config = $.extend({}, that.config, options);
			return that;
		}
		
		,on: function(events, callback){
			return layui.onevent.call(this, MOD_NAME, events, callback);
		}
	}
	,thisIns = function(){
		var that = this
		,options = that.config
		,id = options.id || options.index;
		
		return {
			reload: function(options){
				that.reload.call(that, options);
			}
			,config: options
		}
	}
	,Class = function(options){
		var that = this;
		that.index = ++regionCheckBox.index;
		that.config = $.extend({}, that.config, regionCheckBox.config, options);
		that.render();
	};
	
	
	Class.prototype.config = {
		value: []
		,width: '550px'
		,border: true
		,change: function(result){}
		,ready: function(){}
	};
	
	Class.prototype.render = function(){
		var that = this
		,options = that.config;

		options.elem = $(options.elem);
		
		
		if(!options.elem.hasClass('layui-form') && options.elem.parents('.layui-form').length == 0){
			options.elem.addClass('layui-form');
		}
		options.elem.addClass('layui-regionContent');
		options.elem.css('width', options.width);
		if(!options.border){
			options.elem.css('border', 'none');
		}
		
		options.elem.html(getCheckBoxs(options.name));
		
		//初始值
		if(options.value.indexOf('所有地域') > -1){
			options.elem.find(':checkbox').prop('checked', true);
		}else{
			for(var i=0; i<options.value.length; i++){
				var value = options.value[i]
				,$elem = options.elem.find(':checkbox[value="'+value+'"]');
				
				$elem.prop('checked', true);
				
				if(value.indexOf('-') < 0){ //省
					$elem.parent().find('.city :checkbox').prop('checked', true);
				}
			}
		}
		form.render('checkbox');	
		
		renderParentDom();
		initName(options);
		
		options.elem.find('.parent').mouseover(function() {
			$(this).find('.city').show();
		});
		options.elem.find('.parent').mouseout(function() {
			$(this).find('.city').hide();
		});
		
		form.on('checkbox(regionCheckBox)', function(data) {
			if(data.elem.value == '所有地域') { //选择所有地域
				if(data.elem.checked) {
					options.elem.find(':checkbox').prop('checked', true);
				} else {
					options.elem.find(':checkbox').prop('checked', false);
				}
			} else {
				//选择省（不包括直辖市）
				if($(data.elem).parent().hasClass('parent')) { 
					if(data.elem.checked) {
						var ii = $(data.elem).next();
						$(data.elem).parent().find('.city :checkbox').prop('checked', true);
					} else {
						$(data.elem).parent().find('.city :checkbox').prop('checked', false);
					}
				}
				//选择城市
				if($(data.elem).parent().hasClass('city')) {
					$(data.elem).parents('.parent').attr('name', options.name);
					if(data.elem.checked) {
						var is_all = true;
						$(data.elem).parent().find(':checkbox').each(function(i, item) {
							if(! item.checked) {
								is_all = false;
								return false;
							}
						});
						if(is_all) {
							$(data.elem).parents('.parent').find(':checkbox:first').prop('checked', true);
						}
					} else {
						$(data.elem).parents('.parent').find(':checkbox:first').prop('checked', false);						
					}
				}
				//选择除所有地域外任意
				if(data.elem.checked) { 
					var is_all = true;
					options.elem.find(':checkbox[value!="所有地域"]').each(function(i, item) {
						if(! item.checked) {
							is_all = false;
							return false;
						}
					});
					if(is_all) {
						options.elem.find(':checkbox[value="所有地域"]').prop('checked', true);
					}
				} else {
					options.elem.find(':checkbox[value="所有地域"]').prop('checked', false);
				}
			}
			form.render('checkbox');
			
			renderParentDom();
			initName(options);
			
			options.change(data);
		});
		
		options.ready();
	}
  
	var regionList = {
		'华北': {
			'北京': [],
			'天津': [],
			'河北': ['石家庄', '唐山', '秦皇岛', '邯郸', '邢台', '保定', '张家界', '承德', '廊坊', '衡水', '沧州'],
			'山西': ['太原', '大同', '阳泉', '长治', '晋城', '朔州', '晋中', '运城', '忻州', '临汾', '吕梁'],
			'内蒙古': ['呼和浩特', '包头', '乌海', '赤峰', '通辽', '鄂尔多斯', '呼伦贝尔', '乌兰察布盟', '锡林郭勒盟', '巴彦淖尔盟', '阿拉善盟', '兴安盟']
		},
		'华东': {
			'上海': [],
			'江苏': ['南京', '徐州', '连云港', '淮安', '宿迁', '盐城', '扬州', '泰州', '南通', '镇江', '常州', '无锡', '苏州'],
			'浙江': ['杭州', '宁波', '温州', '嘉兴', '湖州', '绍兴', '金华', '衢州', '舟山', '台州', '丽水'],
			'安徽': ['合肥', '芜湖', '蚌埠', '淮南', '马鞍山', '淮北', '铜陵', '安庆', '黄山', '滁州', '阜阳', '宿州', '六安', '亳州', '池州', '宣城'],
			'福建': ['福州', '厦门', '三明', '莆田', '泉州', '漳州', '南平', '龙岩', '宁德', '平潭'],
			'江西': ['南昌', '景德镇', '萍乡', '九江', '新余', '鹰潭', '赣州', '吉安', '宜春', '抚州', '上饶'],
			'山东': ['济南', '青岛', '淄博', '枣庄', '东营', '潍坊', '烟台', '威海', '济宁', '泰安', '日照', '莱芜', '临沂', '德州', '聊城', '滨州', '菏泽']
		},
		'华中': {
			'河南': ['郑州', '开封', '洛阳', '平顶山', '焦作', '鹤壁', '新乡', '安阳', '濮阳', '许昌', '漯河', '三门峡', '南阳', '商丘', '信阳', '周口', 
					 '驻马店', '济源'],
			'湖北': ['武汉', '黄石', '襄阳', '十堰', '荆州', '宜昌', '荆门', '鄂州', '孝感', '黄冈', '咸宁', '随州', '恩施土家族苗族自治州', '仙桃', '天门', 
					 '潜江', '神农架林区'],
			'湖南': ['长沙', '株洲', '湘潭', '衡阳', '邵阳', '岳阳', '常德', '张家界', '益阳', '郴州', '永州', '怀化', '娄底', '湘西土家族苗族自治州']
		},
		'华南': {
			'广东': ['广州', '深圳', '珠海', '汕头', '韶关', '河源', '梅州', '汕尾', '东莞', '中山', '江门', '佛山', '阳江', '湛江', '茂名', '肇庆', '清远', 
					 '潮州', '揭阳', '云浮', '惠州'],
			'广西': ['南宁', '柳州', '桂林', '梧州', '北海', '防城港', '钦州', '贵港', '玉林', '百色', '贺州', '河池', '来宾', '崇左'],
			'海南': ['海口', '三亚', '文昌', '琼海', '万宁', '五指山', '东方', '儋州', '临高', '澄迈', '定安', '屯昌', '昌江黎族自治县', '白沙黎族自治县', 
					 '琼中黎族苗族自治县', '陵水黎族自治县', '保亭黎族苗族自治县', '乐东黎族自治县', '三沙', '洋浦']
		},
		'西南': {
			'重庆': [],
			'四川': ['成都', '自贡', '攀枝花', '泸州', '德阳', '绵阳', '广元', '遂宁', '内江', '乐山', '南充', '宜宾', '广安', '达州', '眉山', '雅安', '巴中', 
					 '资阳', '阿坝藏族羌族自治州', '甘孜藏族自治州', '凉山彝族族自治州'],
			'贵州': ['贵阳', '六盘山', '遵义', '安顺', '铜仁', '毕节', '黔西南布依族苗族自治州', '黔东南苗族侗族自治州', '黔南布依族苗族自治州'],
			'云南': ['昆明', '曲靖', '玉溪', '宝山', '昭通', '普洱', '临沧', '丽江', '文山壮族苗族自治州', '红河哈尼族彝族自治州', '西双版纳傣族自治州', 
					 '楚雄彝族自治州', '大理白族自治州', '德宏傣族景颇族自治州', '怒江傈傈族自治州', '迪庆藏族自治州'],
			'西藏': ['拉萨', '那曲', '昌都', '山南', '日喀则', '阿里', '林芝']
		},
		'西北': {
			'陕西': ['西安', '铜川', '宝鸡', '咸阳', '渭南', '延安', '汉中', '榆林', '安康', '商洛'],
			'甘肃': ['兰州', '金昌', '白银', '天水', '嘉峪关', '武威', '张掖', '平凉', '酒泉', '庆阳', '定西', '陇南', '甘南藏族自治州', '临夏回族自治州'],
			'青海': ['西宁', '海东', '海北藏族自治州', '海南藏族自治州', '果洛藏族自治州', '玉树藏族自治州', '海西蒙古族藏族自治州', '黄南藏族自治州'],
			'宁夏': ['银川', '石嘴山', '吴忠', '固原', '中卫'],
			'新疆': ['乌鲁木齐', '克拉玛依', '石河子', '阿拉尔', '图木舒克', '五家渠', '吐鲁番', '哈密', '和田', '阿克苏', '喀什', '克孜勒苏柯尔克孜自治州', 
					 '巴音郭楞蒙古自治州', '昌吉回族自治州', '博尔塔拉蒙古自治州', '伊犁哈萨克自治州', '塔城', '阿勒泰']
		},
		'东北': {
			'辽宁': ['沈阳', '大连', '鞍山', '抚顺', '本溪', '丹东', '锦州', '葫芦岛', '营口', '盘锦', '阜新', '辽阳', '铁岭', '朝阳'],
			'吉林': ['长春', '吉林', '四平', '辽源', '通化', '白山', '延边朝鲜族自治州', '白城', '松原'],
			'黑龙江': ['哈尔滨', '齐齐哈尔', '鹤岗', '双鸭山', '鸡西', '大庆', '伊春', '牡丹江', '佳木斯', '七台河', '黑河', '绥化', '大兴安岭']
		},
		'其他': {
			'香港': [],
			'澳门': [],
			'台湾': []
		}
	};
	
	function getCheckBoxs(name){
		var skin = 'primary';
		
		var boxs = '<div class="layui-form-item" style="margin-left:15px;">' +
				   '<input type="checkbox" name="' + name + '" value="所有地域" title="所有地域" lay-skin="' + skin + '" lay-filter="regionCheckBox">' +
				   '</div>';
		
		for(var area in regionList){
			boxs += '<div class="layui-form-item" style="margin-bottom:0;">' +
					'<label class="layui-form-label area">' + area + '：</label>' +
					'<div class="layui-input-block province">' +
					'<ul>';
			for(var province in regionList[area]){
				var city_num = regionList[area][province].length;
				boxs += '<li' + (city_num > 0 ? ' class="parent"' : '') + '>' +
						'<input type="checkbox" name="' + name + '" value="' + province + '" title="' + province + '" lay-skin="' + skin + '" lay-filter="regionCheckBox">';
				
				if(city_num > 0){
					boxs += '<div class="city">';
					for(var i=0; i<city_num; i++){
						var city = regionList[area][province][i];
						boxs += '<input type="checkbox" name="' + name + '" value="' + province + '-' + city + '" title="' + city + '" lay-skin="' + skin + '" lay-filter="regionCheckBox">';
					}	
					boxs += '</div>';					
				}
				
				boxs += '</li>';
			}
			boxs += '</div></div>';
		}
				   
		return boxs;
	}
	
	function initName(options){
		var $elem = $(options.elem);
		
		$elem.find(':checkbox').attr('name', options.name);
		
		if($elem.find(':checkbox[value="所有地域"]').prop('checked')){
			$elem.find(':checkbox[value!="所有地域"]').removeAttr('name');
		}else{
			$('.parent').find(':checkbox:first:checked').each(function() {
				$(this).parent().find('.city :checkbox').removeAttr('name');
			});
		}
	}
	
	function renderParentDom(){
		$('.parent').find(':checkbox:first').not(':checked').each(function() {
			var is_yes_all = true;
			var is_no_all = true;
			$(this).parent().find('.city :checkbox').each(function(i, item) {
				if(item.checked) {
					is_no_all = false;
				} else {
					is_yes_all = false;
				}
			});
			if(!is_yes_all && !is_no_all) {
				$(this).parent().find('.layui-icon:first').removeClass('layui-icon-ok');
				$(this).parent().find('.layui-icon:first').css('border-color', '#5FB878');
				$(this).parent().find('.layui-icon:first').css('background-color', '#5FB878');
			}
		});
	}
	
	regionCheckBox.render = function(options){
		var ins = new Class(options);
		return thisIns.call(ins);
	};
	
	layui.link(layui.cache.base + 'regionCheckBox/regionCheckBox.css?v=1', function(){
		
	}, 'regionCheckBox');

	exports('regionCheckBox', regionCheckBox);
});    