<template>
    <div>
        <Form :model="formData" @submit.native.prevent="save" :label-width="80">
            {{curd_form_group}}
            <FormItem>
                <Button type="primary" html-type="submit" :loading="formLoading">保存</Button>
            </FormItem>
        </Form>
    </div>
</template>
<script>
    export default {
        data() {
            return {
                formData:{{curd_form_field}},
                formLoading: false
            };
        },
        created() {
        },
        methods: {
            save() {
                this.formLoading = true;
                this.$httpRequest("/admin/{{hxc_controller_name}}/add", "post", this.formData).then(res => {
                    this.formLoading = false;
                    if (res.code == 1) {
                        this.$Message.success("操作成功");
                        this.$router.go(-1);
                    } else {
                        this.$Message.error(res.msg);
                    }
                });
            }
        }
    };
</script>