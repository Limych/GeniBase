<?php

/**
 * @group transcriptor
 */
class Tests_Transcriptor_DE extends GB_UnitTestCase {
	function test_transcribes() {
		$data = array(
				'ru' => array(
					'Aachen'		=> 'Ахен',				'Achslach'		=> 'Акслах',
					'Adenauer'		=> 'Аденауэр',			'Ae sch e nbacher'	=> 'Э ш е нбахер',
					'Ahrenviöl'		=> 'Аренфиёль',			'Aichinger'		=> 'Айхингер',
					'Altjürden'		=> 'Альтйюрден',		'Altschul'		=> 'Альтшуль',
					'Andreas'		=> 'Андреас',			'Anjun'			=> 'Аньюн',
					'Avenarius'		=> 'Авенариус',			'Axel'			=> 'Аксель',
					'Aystetten'		=> 'Айштеттен',			'Bach'			=> 'Бах',
					'Baedeker'		=> 'Бедекер',			'Barbara'		=> 'Барбара',
					'Bauer'			=> 'Бауэр',				'Becker'		=> 'Беккер',
					'Behling'		=> 'Белинг',			'Beier'			=> 'Байер',
					'Biehla'		=> 'Била',				'Borussia'		=> 'Боруссия',
					'Buchholz'		=> 'Бухгольц',			'Buckowitz'		=> 'Букковиц',
					'Bujendorf'		=> 'Буендорф',			'Burgkmair'		=> 'Бургмайр',
					'Böll'			=> 'Бёлль',				'Caspar'		=> 'Каспар',
					'Charlotte'		=> 'Шарлотта',			'Chiemsee'		=> 'Кимзе',
					'Christian'		=> 'Кристиан',			'Cilli'			=> 'Цилли',
					'Clemens'		=> 'Клеменс',			'Dhron'			=> 'Дрон',
					'Dick'			=> 'Дик',				'Dietharz'		=> 'Дитхарц',
					'Diez'			=> 'Диц',				'Dolmar'		=> 'Дольмар',
					'Duingen'		=> 'Дуинген',			'Duisburg'		=> 'Дуйсбург',
					'Dähre'			=> 'Дере',				'Ehenfeld'		=> 'Ээнфельд',
					//'Eichhorn'		=> 'Айхгорн', //современный вариант
					'Eichhorn'		=> 'Айххорн',
					'Eisenerzer Alpen'	=> 'Айзенэрцер-Альпен',
					'Ellerbach'		=> 'Эллербах',			'Ephraim'		=> 'Эфраим',
					'Erich'			=> 'Эрих',				'Erkner'		=> 'Эркнер',
					'Eulenberg'		=> 'Ойленберг',			'Eybl'			=> 'Эйбль',
					'Falkenberg'	=> 'Фалькенберг',		'Gauß'			=> 'Гаус',
					'Glewitzer Bodden'	=> 'Глевицер-Бодден',
					'Grimm'			=> 'Гримм',				'Großbarkau'	=> 'Гросбаркау',
					'Hamburg'		=> 'Гамбург',			'Handelsblatt'	=> 'Хандельсблатт',
					'Hans'			=> 'Ханс',				'Hitler'		=> 'Гитлер',
					//'Hochhuth'		=> 'Хоххут', //современный вариант
					'Hofsee'		=> 'Хофзе',
					'Hohenlohe'		=> 'Хоэнлоэ',			'Häusler'		=> 'Хойслер',
					//'Hörnle'		=> 'Хёрнле', // h как х, если произносится
					'Inn'			=> 'Инн',
					'Itzehoe'		=> 'Итцехо',			'Jade'			=> 'Яде',
					'Jehserig'		=> 'Езериг',			'Jiedlitz'		=> 'Йидлиц',
					'Joseph'		=> 'Йозеф',				'Jöhstadt'		=> 'Йёштадт',
					'Kalksee'		=> 'Калькзе',			'Kleve'			=> 'Клеве',
					'Klützer'		=> 'Клютцер',			'Koopmann'		=> 'Копман',
					'Käthe'			=> 'Кете',				'Köpenick'		=> 'Кёпеник',
					'Laer'			=> 'Лар',				'Landsberg'		=> 'Ландсберг',
					'Lembeck'		=> 'Лембекк',			'Lessing'		=> 'Лессинг',
					'Loitsche'		=> 'Лоче',				'Ludwigsstadt'	=> 'Людвигсштадт',
					'Magdeburg'		=> 'Магдебург',			'Marchwitza'	=> 'Мархвитца',
					'Maria'			=> 'Мария',				'Marienberg'	=> 'Мариенберг',
					'Meyrink'		=> 'Майринк',			'Mutterstadt'	=> 'Муттерштадт',
					'Nationalzeitung'	=> 'Национальцайтунг',
					'Naumann'		=> 'Науман',			'Nebel'			=> 'Небель',
					'Netzsch'		=> 'Неч',				'Niedernjesa'	=> 'Нидерньеза',
					'Nietzsche'		=> 'Ницше',				'Nietzsche'		=> 'Ницше',
					'Oberjettingen'	=> 'Оберъеттинген',		'Oelze'			=> 'Эльце',
					'Oetker'		=> 'Эткер',				'Ohne'			=> 'Оне',
					'Oie'			=> 'Ойе',				'Oltes'			=> 'Ольтес',
					'Oybin'			=> 'Ойбин',				'Papststein'	=> 'Папстштайн',
					'Quedlinburg'	=> 'Кведлинбург',		'Rainer'		=> 'Райнер',
					'Regenberger'	=> 'Регенбергер',		'Ryck'			=> 'Рик',
					'Schiller'		=> 'Шиллер',			'Schtschedrin'	=> 'Щедрин',
					'Semper'		=> 'Земпер',			'Spiegel'		=> 'Шпигель',
					'Spree'			=> 'Шпре',				'Starhemberg'	=> 'Штаремберг',
					'Strausberg'	=> 'Штраусберг',		'Tatschow'		=> 'Тачов',
					'Tellkoppe'		=> 'Теллькоппе',		'Theo'			=> 'Тео',
					'Thyssen'		=> 'Тиссен',			'Treptow'		=> 'Трептов',
					'Trude'			=> 'Труде',				'Tzscheetzsch'	=> 'Чеч',
					'Uenze'			=> 'Инце',				'Uhlberg'		=> 'Ульберг',
					'Ulm'			=> 'Ульм',				'Volkmar'		=> 'Фолькмар',
					'Wagner'		=> 'Вагнер',			'Weihenstephan'	=> 'Вайенштефан',
					'Wulf'			=> 'Вульф',				'Xaver'			=> 'Ксавер',
					'Zeidler'		=> 'Цайдлер',			'Zschopau'		=> 'Чопау',
					'Zwickau'		=> 'Цвиккау',			'Öttinger'		=> 'Эттингер',
					'Überweg'		=> 'Ибервег',						
				)
		);
		foreach ($data as $to_lang => $pairs){
			foreach ($pairs as $src => $res)
				$this->assertEquals($res, GB_Transcriptor::transcript($src, 'de', $to_lang));
		}
	}

/*	function test_tranliterates() {
		$data = array(
				'ru' => array(
					''	=> '',
				)
		);
		foreach ($data as $to_lang => $pairs){
			foreach ($pairs as $src => $res)
				$this->assertEquals($res, GB_Transcriptor::transcript($src, 'pl', $to_lang, GB_Transcriptor::MODE_TRANSLITERATE));
		}
	}/**/
}
