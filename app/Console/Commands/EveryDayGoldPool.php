<?php

namespace App\Console\Commands;
use App\GoldFlow;
use App\Member;
use App\GoldChangeDay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EveryDayGoldPool extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crontab:gold_pool';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每天统计金币池数量，用户手中金币数量，燃烧金币数量一次';

    /**
     * @var
     */
    protected $model;
    /**
     * @var
     */
    protected $model_change_day;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->model = new GoldFlow;
        $this->model_change_day = new GoldChangeDay;
        DB::transaction(function () {
            // 上一次统计数据
            $oLastGoldPool = $this->model_change_day->getLastData();

            // 金币池出去金币
            $fOutGold = $this->getGoldPullOut();
            // 金币池进入金币
            $fInGold  = $this->getGoldPullIn();
            // 用户手中所有金币
            $fMemberGoldNum = $this->getAllMemberGold();
            // 燃烧金币
            $fBurnGoldNum = $this->getBurnGoldSum();
            // 金币池数量
            $fTmp = bcsub($oLastGoldPool['gold'], $fOutGold, 2);
            $fGold = bcadd($fTmp, $fInGold,2);
            // 更新
            $this->goldPullOutUpdate();
            $this->goldPullInUpdate();
            // 保存
            $this->model_change_day->gold = $fGold;
            $this->model_change_day->user_sum_gold = $fMemberGoldNum;
            $this->model_change_day->burn_gold = $fBurnGoldNum;
            $this->model_change_day->date = date('Y-m-d');
            $this->model_change_day->save();
            // 清空redis
            redis_del(config('czf.redis_key.s5'));

        });
    }

    /**
     * @ 用户手中所有金币
     */
    public function getAllMemberGold()
    {
        $member = new Member;
        return $member->lockForUpdate()->sum('gold') ?? 0.00;
    }

    /**
     * @return float
     * @see 金币池出 加锁
     */
    public function getGoldPullOut():float
    {
        // 领取扣除
        $fGoldA = $this->getAutoGoldSum();
        $fGoldR = $this->getRechargeNum(9);
        return bcadd($fGoldA,$fGoldR,2);
    }

    public function goldPullOutUpdate()
    {
        $this->model->where(['is_statistical' => 0,'type'=>9])->update(['is_statistical'=>1]);
        $this->model->where(['is_statistical' => 0,'type'=>4])->update(['is_statistical'=>1]);
    }

    /**
     * @param int $iType 9 后台充值增加 10后台充值减少
     * @return float
     * @see 金币充值
     */
    public function getRechargeNum(int $iType):float
    {
        return $this->model->where(['is_statistical' => 0,'type'=>$iType])->lockForUpdate()->sum('gold') ?? 0.00;
    }

    /**
     * @see 自动领取
     * @return float
     */
    public function getAutoGoldSum():float
    {
        return $this->model->where(['is_statistical' => 0,'type'=> 4])->lockForUpdate()->sum('gold') ?? 0.00;
    }

    /**
     * @return float金币池进
     */
    public function getGoldPullIn():float
    {
        // 金币燃烧返回金币池
        $bNum = $this->getReturnBurnGoldSum();
        // 金币购物返回金币池
        $sNum = $this->getReturnShopGoldNum();
        // 充值扣除返回金币池
        $rNum = $this->getRechargeNum(10);

        return bcadd(bcadd($bNum,$sNum,5),$rNum,2);
    }

    public function goldPullInUpdate()
    {
        $this->model->where(['is_statistical' => 0,'type'=>5])->update(['is_statistical'=>1]);
        $this->model->where(['is_statistical' => 0,'type'=>12])->update(['is_statistical'=>1]);
        $this->model->where(['is_statistical' => 0,'type'=>10])->update(['is_statistical'=>1]);
    }

    /**
     * @return float
     * @彻底燃烧金币
     */
    public function getBurnGoldSum():float
    {
        return $this->model->where('type',11)->lockForUpdate()->sum('gold') ?? 0.00;
    }

    /**
     * @return float
     * @see 购物消耗
     * @see 购物金币流向金币池
     */
    public function getReturnShopGoldNum():float
    {
        return $this->model->where(['is_statistical' => 0,'type'=>12])->lockForUpdate()->sum('gold') ?? 0.00;
    }

    /**
     * @return float
     */
    public function getReturnBurnGoldSum():float
    {
        return $this->model->where(['is_statistical' => 0,'type'=>5])->lockForUpdate()->sum('gold') ?? 0.00;
    }

}