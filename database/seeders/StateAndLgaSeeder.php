<?php

namespace Database\Seeders;

use App\Models\State;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class StateAndLgaSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Abia' => ['code' => 'AB', 'lgas' => ['Aba North', 'Aba South', 'Umuahia North', 'Umuahia South']],
            'Adamawa' => ['code' => 'AD', 'lgas' => ['Demsa', 'Fufore', 'Ganye', 'Yola North', 'Yola South']],
            'Akwa Ibom' => ['code' => 'AK', 'lgas' => ['Eket', 'Ikot Ekpene', 'Uyo', 'Oron']],
            'Anambra' => ['code' => 'AN', 'lgas' => ['Awka North', 'Awka South', 'Onitsha North', 'Onitsha South', 'Nnewi North']],
            'Bauchi' => ['code' => 'BA', 'lgas' => ['Bauchi', 'Katagum', 'Misau', 'Jama\'are']],
            'Bayelsa' => ['code' => 'BY', 'lgas' => ['Brass', 'Ekeremor', 'Kolokuma/Opokuma', 'Yenagoa']],
            'Benue' => ['code' => 'BE', 'lgas' => ['Gboko', 'Makurdi', 'Otukpo', 'Vandeikya']],
            'Borno' => ['code' => 'BO', 'lgas' => ['Maiduguri', 'Bama', 'Biur', 'Gwoza']],
            'Cross River' => ['code' => 'CR', 'lgas' => ['Calabar Municipal', 'Calabar South', 'Akamkpa', 'Ogoja']],
            'Delta' => ['code' => 'DE', 'lgas' => ['Asaba', 'Warri North', 'Warri South', 'Uvwie', 'Sapele']],
            'Ebonyi' => ['code' => 'EB', 'lgas' => ['Abakaliki', 'Afikpo North', 'Afikpo South', 'Ezza North']],
            'Edo' => ['code' => 'ED', 'lgas' => ['Benin City', 'Esan North-East', 'Esan West', 'Oredo']],
            'Ekiti' => ['code' => 'EK', 'lgas' => ['Ado Ekiti', 'Ikere', 'Oye', 'Ekiti West']],
            'Enugu' => ['code' => 'EN', 'lgas' => ['Enugu East', 'Enugu North', 'Enugu South', 'Nsukka', 'Udi']],
            'FCT' => ['code' => 'FC', 'lgas' => ['Abuja Municipal', 'Bwari', 'Gwagwalada', 'Kuje', 'Abaji', 'Kwali']],
            'Gombe' => ['code' => 'GB', 'lgas' => ['Gombe', 'Akko', 'Balanga', 'Billiri']],
            'Imo' => ['code' => 'IM', 'lgas' => ['Owerri Municipal', 'Owerri North', 'Owerri West', 'Orlu', 'Okigwe']],
            'Jigawa' => ['code' => 'JI', 'lgas' => ['Dutse', 'Hadejia', 'Kazaure', 'Ringim']],
            'Kaduna' => ['code' => 'KD', 'lgas' => ['Kaduna North', 'Kaduna South', 'Zaria', 'Sabon Gari']],
            'Kano' => ['code' => 'KN', 'lgas' => ['Fagge', 'Dala', 'Gwale', 'Nassarawa', 'Tarauni', 'Kano Municipal']],
            'Katsina' => ['code' => 'KT', 'lgas' => ['Katsina', 'Daura', 'Funtua', 'Malumfashi']],
            'Kebbi' => ['code' => 'KE', 'lgas' => ['Birnin Kebbi', 'Argungu', 'Yauri', 'Zuru']],
            'Kogi' => ['code' => 'KO', 'lgas' => ['Lokoja', 'Okene', 'Idah', 'Adavi']],
            'Kwara' => ['code' => 'KW', 'lgas' => ['Ilorin East', 'Ilorin South', 'Ilorin West', 'Offa']],
            'Lagos' => ['code' => 'LA', 'lgas' => ['Ikeja', 'Alimosho', 'Kosofe', 'Ikorodu', 'Lagos Island', 'Lagos Mainland', 'Surulere']],
            'Nasarawa' => ['code' => 'NA', 'lgas' => ['Lafia', 'Karu', 'Keffi', 'Akwanga']],
            'Niger' => ['code' => 'NI', 'lgas' => ['Minna', 'Bida', 'Suleja', 'Kontagora']],
            'Ogun' => ['code' => 'OG', 'lgas' => ['Abeokuta North', 'Abeokuta South', 'Ijebu Ode', 'Sagamu', 'Ota']],
            'Ondo' => ['code' => 'ON', 'lgas' => ['Akure North', 'Akure South', 'Ondo West', 'Owo']],
            'Osun' => ['code' => 'OS', 'lgas' => ['Osogbo', 'Ilesa East', 'Ilesa West', 'Ife Central', 'Ife East']],
            'Oyo' => ['code' => 'OY', 'lgas' => ['Ibadan North', 'Ibadan North-East', 'Ibadan South-West', 'Ogbomosho North', 'Oyo West']],
            'Plateau' => ['code' => 'PL', 'lgas' => ['Jos North', 'Jos South', 'Jos East', 'Pankshin']],
            'Rivers' => ['code' => 'RI', 'lgas' => ['Port Harcourt', 'Obio-Akpor', 'Bonny', 'Eleme', 'Oyigbo']],
            'Sokoto' => ['code' => 'SO', 'lgas' => ['Sokoto North', 'Sokoto South', 'Wamako', 'Gwadabawa']],
            'Taraba' => ['code' => 'TA', 'lgas' => ['Jalingo', 'Wukari', 'Sardauna', 'Bali']],
            'Yobe' => ['code' => 'YO', 'lgas' => ['Damaturu', 'Potiskum', 'Gashua', 'Nguru']],
            'Zamfara' => ['code' => 'ZA', 'lgas' => ['Gusau', 'Kaura Namoda', 'Maradun', 'Talata Mafara']],
        ];

        foreach ($data as $stateName => $info) {
            $state = State::create([
                'name' => $stateName,
                'code' => $info['code'],
            ]);

            foreach ($info['lgas'] as $lgaName) {
                $lga = Lga::create([
                    'state_id' => $state->id,
                    'name' => $lgaName,
                ]);

                // Seed dummy wards for testing
                Ward::create([
                    'lga_id' => $lga->id,
                    'name' => 'Ward 1',
                ]);
                Ward::create([
                    'lga_id' => $lga->id,
                    'name' => 'Ward 2',
                ]);
            }
        }
    }
}
