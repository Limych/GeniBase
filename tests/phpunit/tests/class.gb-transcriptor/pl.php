<?php

/**
 * @group transcriptor
 */
class Tests_Transcriptor_PL extends GB_UnitTestCase {
	function test_transcribes() {
		$data = array(
				'ru' => array(
					'Adam'			=> 'Адам',				'Adrian'		=> 'Адриан',
					'Andrzej'		=> 'Анджей',			'Antoni'		=> 'Антоний',
					'Bądkowo'		=> 'Бондково',			'Balcerzak'		=> 'Бальцежак',
					'Bogdan'		=> 'Богдан',			'Brodzka'		=> 'Бродская',
					'Brodzki'		=> 'Бродский',			'Celina'		=> 'Целина',
					'Celjowski'		=> 'Цельовский',		'Chajęcki'		=> 'Хаенцкий',
					'Chamiec'		=> 'Хамец',				'Ciągło'		=> 'Ционгло',
					'Ciępka'		=> 'Цемпка',			'Cieślak'		=> 'Цесляк',
					'Ciołek'		=> 'Циолек',			'Cirlić'		=> 'Цирлиць',
					'Czerniatowicz'	=> 'Чернятович',		'Dąbkowo'		=> 'Домбково',
					'Derdowski'		=> 'Дердовский',		'Dziąba'		=> 'Дзёмба', //Dziąmba - по-моему неверно
					'Dzianisz'		=> 'Дзяниш',			'Dżunkowski'	=> 'Джунковский',
					'Emil'			=> 'Эмиль',				'Eugeniusz'		=> 'Эугениуш',
					'Filip'			=> 'Филип',				'Gjądła'		=> 'Гьондла',
					'Gotard'		=> 'Готард',			'Huta'			=> 'Хута',
					'Jacek'			=> 'Яцек',				'Jączkowski'	=> 'Йончковский',
					'Jakóbik'		=> 'Якубик',			'Jębrzycki'		=> 'Ембжицкий',
					'Joniec'		=> 'Йонец',				'Juliusz'		=> 'Юлиуш',
					'Kopjewski'		=> 'Копьевский',		'Kościńska'		=> 'Косьциньская',
					'Krystyna'		=> 'Кристина',			'Krzysztof'		=> 'Кшиштоф',
					'Ksawery'		=> 'Ксаверий',			'Kyrljęcik'		=> 'Кырльенцик',
					'Lacaz'			=> 'Ляцаз',				'Łodyński'		=> 'Лодыньский',
					'Lorentowicz'	=> 'Лёрентович',		'Lubecki'		=> 'Любецкий',
					'Maciejowice'	=> 'Мацеёвице',			'Marian'		=> 'Мариан',
					'Mariusz'		=> 'Мариуш',			'Niedźwiedź'	=> 'Недзведзь',
					'Osjaków'		=> 'Осьякув',			'Piękna'		=> 'Пенкна',
					'Przegląd'		=> 'Пшеглёнд',			'Przyjaciółka'	=> 'Пшиячулка',
					'Pyru'			=> 'Пыру',				'Roman'			=> 'Роман',
					'Rzeczywistość'	=> 'Жечивистость',		'Rzytka'		=> 'Житка',
					'Solec'			=> 'Солец',				'Stanisław'		=> 'Станислав',
					'Stepowska'		=> 'Стемповская',		'Stępowski'		=> 'Стемповский',
					'Szczawiej'		=> 'Щавей',				'Szymon'		=> 'Шимон',
					'Wałęsa'		=> 'Валенса',			'Władysław'		=> 'Владислав',
					'Wojciech'		=> 'Войцех',			'Zając'			=> 'Зайонц',
					'Zbigniew'		=> 'Збигнев',			'Ziobro'		=> 'Зёбро',
					'Żołnierz wolności'	=> 'Жолнеж вольности',	'Żorż'		=> 'Жорж',
					'Zosia'			=> 'Зося',				'Źródłowski'	=> 'Зьрудловский',
					'Związkowiec'	=> 'Звёнзковец',		'Życie'			=> 'Жиче',
					'Сiąpała'		=> 'Циомпала',
				)
		);
		foreach ($data as $to_lang => $pairs){
			foreach ($pairs as $src => $res)
				$this->assertEquals($res, GB_Transcriptor::transcript($src, 'pl', $to_lang));
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
