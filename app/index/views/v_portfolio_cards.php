<?php
if (!ENV_SITE) {
	http_response_code(404); die;
}
$arrPortfolio = [
	'tdelit.ru' => [
		'desc' => 'Маркетплейс медицинской одежды ELIT МАРКЕТ Более 10 000 Товаров для медицинских работников.',
		'url' => 'https://tdelit.ru',
		'img' => '/uploads/images/tdelit.webp'
	],
	'winrace.ru' => [
		'desc' => 'Маркетплейс товаров для спорта и туризма.',
		'url' => 'https://winrace.ru',
		'img' => '/uploads/images/winrace.webp'
	],
	'myasniki.ru' => [
		'desc' => '«Мясники.ру» предлагает ножи, принадлежности для разделки мяса и кулинарии, кольчужные перчатки и фартуки для убойных цехов, а так же заточные станки.',
		'url' => 'https://myasniki.ru',
		'img' => '/uploads/images/myasniki.webp'
	],
	'igromir.net' => [
		'desc' => 'ООО «ИГРОМИР» работает на рынке оптовой и розничной торговли детскими игрушками с 2004г.',
		'url' => 'https://igromir.net',
		'img' => '/uploads/images/igromir.webp'
	],
	'rgw-magazin.ru' => [
		'desc' => 'Компания RGW - Стильные Душевые — крупный производитель душевых кабин, дверей, перегородок, углов.',
		'url' => 'https://rgw-magazin.ru',
		'img' => '/uploads/images/rgw-magazin.webp'
	],
	'delta-paneli.ru' => [
		'desc' => 'Мультивитринный интернет-магазин delta-paneli.ru специализируется на продаже оборудования для солнечных электростанций.',
		'url' => 'https://delta-paneli.ru/',
		'img' => '/uploads/images/delta_paneli.webp'
	],
];
foreach ($arrPortfolio as $name => $item) {
?>
<div class="col-md-4 mb-4">
	<div class="card h-100">
		<img src="<?=$item['img']?>" class="card-img-top" alt="<?=$name?>" width="330" height="169">
		<div class="card-body">
			<span class="card-title h3"><?=$name?></span>
			<p class="card-text"><?=$item['desc']?></p>
		</div>
		<div class="card-footer">
			<a href="<?=$item['url']?>" class="btn btn-primary" target="_BLANK" aria-label="<?=$item['desc']?>">Перейти на сайт</a>
		</div>
	</div>
</div>
<?php
	}
?>