<?php

namespace App\Tests\Infrastructure\ValueObject\Geography;

use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class EncodedPolylineTest extends TestCase
{
    use MatchesSnapshots;

    public function testGetStartingCoordinate(): void
    {
        $polyline = EncodedPolyline::fromString('pr_fA{ybz^wA_DeAgE@sAr@mEyBkFLo@f@_@~CC\qEZi@~@SnALnDlBJ~B|Bz@aAbDRn@j@ZfBHrEa@rAaAz@sABq@_AgBKeARm@hCwBTsAKgBiBmFoCqCkBkAOkAlA}EhEeBz@}AJ_BS_B]s@oCeCUs@xB_CI{CnI_T^_BHgCQiAc@k@iHyC{@aBQqBlGoNbAsDvCw@bCr@dAEv@i@bB{CxCGrBgAd@}A?cBcDoFd@gA`Ao@`AGz@d@lFvIdAl@h@E|@u@bAsD`@g@fBZ~@`ABhE`@h@pB`ARdAWp@eC`Aa@bAC`KiAvNEjDvDzE`@fBZRxFh@~L{C~Ah@x@jF\bN_@|AgB|AK`B^tFt@nB~An@zBKzAn@lD`@jBxA`@pCfA`AdAc@vCaJnAmAfANtAbA`@pC~EVlCfDnCGhAq@`@{@LuAMq@yBaBgA@aA\sBhCgEEm@WYgC_BgAeAKmAfAyCbJaAf@cAeAa@kCgBuAsDg@_Bo@_CH_Bq@o@{BSwGPs@nBoBP_Dq@gNc@aC_By@aM|CyFe@_@Qc@kBqCwCe@aArAsSBsKh@_AtBs@^_A_@qAiC}ACiEoAmAiBSa@j@}@nD{@t@e@DkAy@eFmIaAe@}@FqAx@a@~@Pt@pCdE@tAa@`BuBdAuCDkBbDk@`@iABuBs@kDz@uAvEyFdMDjB`ApBfA|@~E|Aj@v@NfAg@rEmI`TKt@X|A_ChCZfAdDpDRjBMvA_ArAaE~Ak@lAg@jCPxAvDpCxAlBpA~DJxBa@~AoBpA]p@FjAdA`CkAvBw@n@kE`@oBCo@[OcAbCwEpBiA~Ca@lCmAh@i@\cBSoCoAiDwAmBwDqCMiA`@aCl@wAfEeBz@sANiBWgBmDuDQw@|BaCUaBH{@nIaTXmAJoCSiAe@m@cFaBiA}@_AuBC_BjG_NpAcEnCm@vBr@jAAz@m@tAsChDOpBeAb@yAEoB_D_F\gAnAw@~@Ez@d@|E`ItA`Ah@Gz@w@|AuE|APhA|@Pv@E~ClCfBTjA[r@aCz@a@hA?lKwAdSn@tApCvCXzA\VvFj@~L{Cx@Hj@f@l@lDh@rMMdCqBhBSv@PrFZtB^t@vAn@lCGvAl@rDf@~AhAd@pCjAhAhAq@rCuIfAiAnALnA|@j@zCdFVnBvCd@PxBK`Ag@h@}@NyAs@aBqB}@mBf@}A~BkAB');

        $this->assertEquals(
            Coordinate::createFromLatAndLng(
                Latitude::fromString('-11.63577'),
                Longitude::fromString('166.97262'),
            ),
            $polyline->getStartingCoordinate()
        );
    }

    public function testFromCoordinates(): void
    {
        $this->assertEquals(
            EncodedPolyline::fromString('_p~iF~ps|U_ulLnnqC_mqNvxq`@'),
            EncodedPolyline::fromCoordinates([
                [38.5, -120.2],
                [40.7, -120.95],
                [43.252, -126.453],
            ])
        );
    }

    public function testEncodeIsInverseOfDecode(): void
    {
        $coordinates = [
            [-11.63577, 166.97262],
            [-11.63533, 166.97342],
            [50.12345, 4.56789],
        ];

        $this->assertEqualsWithDelta(
            $coordinates,
            EncodedPolyline::fromCoordinates($coordinates)->decodeAndPairLatLng(),
            0.00001
        );
    }

    public function testDecode(): void
    {
        $polyline = EncodedPolyline::fromString('pr_fA{ybz^wA_DeAgE@sAr@mEyBkFLo@f@_@~CC\qEZi@~@SnALnDlBJ~B|Bz@aAbDRn@j@ZfBHrEa@rAaAz@sABq@_AgBKeARm@hCwBTsAKgBiBmFoCqCkBkAOkAlA}EhEeBz@}AJ_BS_B]s@oCeCUs@xB_CI{CnI_T^_BHgCQiAc@k@iHyC{@aBQqBlGoNbAsDvCw@bCr@dAEv@i@bB{CxCGrBgAd@}A?cBcDoFd@gA`Ao@`AGz@d@lFvIdAl@h@E|@u@bAsD`@g@fBZ~@`ABhE`@h@pB`ARdAWp@eC`Aa@bAC`KiAvNEjDvDzE`@fBZRxFh@~L{C~Ah@x@jF\bN_@|AgB|AK`B^tFt@nB~An@zBKzAn@lD`@jBxA`@pCfA`AdAc@vCaJnAmAfANtAbA`@pC~EVlCfDnCGhAq@`@{@LuAMq@yBaBgA@aA\sBhCgEEm@WYgC_BgAeAKmAfAyCbJaAf@cAeAa@kCgBuAsDg@_Bo@_CH_Bq@o@{BSwGPs@nBoBP_Dq@gNc@aC_By@aM|CyFe@_@Qc@kBqCwCe@aArAsSBsKh@_AtBs@^_A_@qAiC}ACiEoAmAiBSa@j@}@nD{@t@e@DkAy@eFmIaAe@}@FqAx@a@~@Pt@pCdE@tAa@`BuBdAuCDkBbDk@`@iABuBs@kDz@uAvEyFdMDjB`ApBfA|@~E|Aj@v@NfAg@rEmI`TKt@X|A_ChCZfAdDpDRjBMvA_ArAaE~Ak@lAg@jCPxAvDpCxAlBpA~DJxBa@~AoBpA]p@FjAdA`CkAvBw@n@kE`@oBCo@[OcAbCwEpBiA~Ca@lCmAh@i@\cBSoCoAiDwAmBwDqCMiA`@aCl@wAfEeBz@sANiBWgBmDuDQw@|BaCUaBH{@nIaTXmAJoCSiAe@m@cFaBiA}@_AuBC_BjG_NpAcEnCm@vBr@jAAz@m@tAsChDOpBeAb@yAEoB_D_F\gAnAw@~@Ez@d@|E`ItA`Ah@Gz@w@|AuE|APhA|@Pv@E~ClCfBTjA[r@aCz@a@hA?lKwAdSn@tApCvCXzA\VvFj@~L{Cx@Hj@f@l@lDh@rMMdCqBhBSv@PrFZtB^t@vAn@lCGvAl@rDf@~AhAd@pCjAhAhAq@rCuIfAiAnALnA|@j@zCdFVnBvCd@PxBK`Ag@h@}@NyAs@aBqB}@mBf@}A~BkAB');

        $this->assertMatchesJsonSnapshot($polyline->decode());
    }
}
