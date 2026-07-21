<?php

namespace App\Tests\Domain\Activity\Route;

use App\Domain\Activity\Route\RouteGeographyAnalyzer;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use PHPUnit\Framework\TestCase;

class RouteGeographyAnalyzerTest extends TestCase
{
    public function testAnalyzeForPolyline(): void
    {
        $analyzer = new RouteGeographyAnalyzer();

        $this->assertEquals(
            ['BE', 'NL'],
            $analyzer->analyzeForPolyline(EncodedPolyline::fromString('qr{yH}ftUFpAl@\pG}AbEwBgBi\pBaJC}En@q@JlAiDdPbBp\hF~s@Of^tGx@`IfCYJtBfBFr@_Bb`@jHxAZj@qGfIqE`JsCnK_BlM_@bO^n]YlEq@hC`BjD`@vE[fEk@rBgAbPeEdb@[bAC|CiAlMF`rAtAlQDtIdCrTtIre@tCvMn@bBb@DhAlBwAsBjApDM`@kAp@?fB`BdBpCrF~ClEpEbEv_@vRlItF~D_OfAiC|AaM~@SdGbGjAgAfBm@jE{IfFwPhCyGlDcF~By@pBDXf@pA\LbDN\~CbBhQ`O~M~CpBu@pFeIdNiEfGdAbASrEyClCB~BhBrAX|L[zFwA`@~Bb@rI`AtChB|@vF[fAl@VnCnBDdMcDlJnAtEaBdl@{JdTyElTKxIZHlAa@bPZhA~c@}BxAoDf@WrEvH`BRl@jBf@F|D]lAy@jP{Tj@W^cARAzGlFjMvKzS~QbIpC_@tCBx@~BfBzGdMrC`ItDjHj@NrA{BvAsAj@GfEhNBtGVnAZl@j@Nr@i@|ElL|D~GfElF~EjEvF|C~tCzmApAoJ|HpDhLnC|NvAnCgArB`AhABhLu@vAcAImDV[~Lk@n^iEbGpAhHqAfjG|lHRz@`AnAvBs@hAnAdA`@bMx@zJiCt@}@tBZf]}GhReAzLHVfGtAtJTdEu@xj@X`MCjGfGpq@MpEwApMIzWg@xDj@pA~BvPn@dLpE|Tr@~QtBhK`BxFdElIfCrOnAzLn@fWhDr\pAxHdIj[rCbZfBhFdDdFhJ`TrOvk@nBs@n@`DT`GdEpR~CjLvG|Q~A~BvOjZdBjAr@hDpFrLfKhRpApEfBzPfAtEbNtXD~@SR\hBfBvBbLlSrFzG`NbNhb@~^pF|FdGtIn[jj@pDjE~DxBrQnEjM^dCrAz@fBdHdYtDzLbHjH~MfPbJbOdCnHhD~FnA`@jPnNtCe@nJ`MxC`CU`Fb@x@p@~DbQnJ`BlBf@pBDbCm@va@PlEbAxEpLxz@jApF|d@pkA`Vbo@~Prf@pB|Djr@|lBnEtKpHdKbiAtvAVLn@u@z@Zn@i@~BnCjCbDeAzCHn@hmAt{ApAtAt@aAZVheBrwB|FdIf@YNs@vL_I~@OlBbClDlCrC`DxJfBrOdGl@t@nDhKvCxElOo@|JgCnCtC`GvBvDjEzChBz@rAFzAl@JbAzAvC_EjAeFv@eBzA}B`By@nBB`QdE~QaDzPwE~QPd@`@_@vDBnE`BbNhPj[n@PbAuDzA`@t@mAdDpEhC{E`CnC')),
        );
    }

    public function testAnalyzeForInvalidPolyline(): void
    {
        $analyzer = new RouteGeographyAnalyzer();

        $this->assertEquals(
            [],
            $analyzer->analyzeForPolyline(EncodedPolyline::fromString('}ftUqr{yH'))
        );
    }
}
